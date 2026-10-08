<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\Portal\Bin;
use App\Models\Portal\Carton;
use App\Models\Portal\CartonLineConfirm;
use App\Models\Portal\Item;
use App\Models\Portal\ItemExtend;
use App\Models\Portal\RealtimeInventoryDetail;
use App\Models\StockGenerationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class StockGenerationService
{
    private const LOCATION_ID = 22;

    private const WAREHOUSE_ID = 3;

    private const CONFIRMED_BY = 491;

    private const POSITION = 1;

    /**
     * Process a single entry and generate stock in the portal system.
     */
    public function generate(Entry $entry): void
    {
        $operationId = Str::uuid()->toString();

        try {
            if ($entry->type === Entry::TYPE_CARTON) {
                $this->processCartonType($entry, $operationId);
            } elseif ($entry->type === Entry::TYPE_TPIN) {
                $this->processTpinType($entry, $operationId);
            } else {
                throw new \RuntimeException("Unsupported entry type: {$entry->type}");
            }
        } catch (Throwable $e) {
            $entry->stock_generated = false;
            $entry->error = $e->getMessage();
            $entry->save();
        }
    }

    /**
     * Carton type: look up the existing carton by CartonNumber, then update
     * matching RealtimeInventoryDetail rows.
     */
    private function processCartonType(Entry $entry, string $operationId): void
    {
        $entry->load('products');

        $carton = Carton::where('CartonNumber', $entry->code)->first();
        if ($carton === null) {
            throw new \RuntimeException("Carton not found in portal for CartonNumber: {$entry->code}");
        }

        $bin = Bin::where('CartonID', $carton->CartonID)->first();
        if ($bin === null) {
            throw new \RuntimeException("Bin not found in portal for CartonID: {$carton->CartonID}");
        }

        $itemIds = [];
        foreach ($entry->products as $product) {
            $itemId = Item::where('TPIN', $product->tpin)->value('ItemID');
            if ($itemId === null) {
                throw new \RuntimeException("Item not found in portal for TPIN: {$product->tpin}");
            }
            $itemIds[$product->tpin] = $itemId;
        }

        $logs = DB::connection('portal')->transaction(function () use ($entry, $bin, $itemIds): array {
            $records = [];

            foreach ($entry->products as $product) {
                $itemId = $itemIds[$product->tpin];

                $detail = RealtimeInventoryDetail::where('BinId', $bin->BinID)
                    ->where('ItemId', $itemId)
                    ->first();

                if ($detail === null) {
                    throw new \RuntimeException(
                        "RealtimeInventoryDetail not found for BinID {$bin->BinID} and ItemID {$itemId}"
                    );
                }

                $beforeOnHand = $detail->QtyOnHand;
                $beforeAvailable = $detail->QtyAvailable;

                $detail->update([
                    'QtyOnHand' => $product->quantity,
                    'QtyAvailable' => $product->quantity,
                    'LastModificationTime' => now('UTC'),
                ]);

                $this->markItemStockForSync($itemId);

                $records[] = [
                    'operation_id' => null,
                    'entry_id' => $entry->id,
                    'type' => $entry->type,
                    'code' => $entry->code,
                    'action' => 'updated',
                    'carton_id' => (int) $bin->CartonID,
                    'bin_id' => (int) $bin->BinID,
                    'item_id' => (int) $itemId,
                    'inventory_detail_id' => $detail->Id,
                    'tpin' => $product->tpin,
                    'location_code' => $entry->location_code,
                    'quantity' => $product->quantity,
                    'qty_on_hand_before' => $beforeOnHand !== null ? (int) $beforeOnHand : null,
                    'qty_on_hand_after' => (int) $product->quantity,
                    'qty_available_before' => $beforeAvailable !== null ? (int) $beforeAvailable : null,
                    'qty_available_after' => (int) $product->quantity,
                ];
            }

            return $records;
        });

        DB::transaction(function () use ($entry, $carton, $operationId, $logs): void {
            foreach ($logs as $log) {
                $log['operation_id'] = $operationId;
                StockGenerationLog::create($log);
            }

            $entry->carton_id = (int) $carton->CartonID;
            $entry->stock_generated = true;
            $entry->error = null;
            $entry->save();
        });
    }

    /**
     * Tpin type: always create a new carton, bin, line confirm and inventory detail.
     */
    private function processTpinType(Entry $entry, string $operationId): void
    {
        $itemId = Item::where('TPIN', $entry->code)->value('ItemID');
        if ($itemId === null) {
            throw new \RuntimeException("Item not found in portal for TPIN: {$entry->code}");
        }

        $locationBinId = Bin::where('BinNumber', $entry->location_code)
            ->where('LocationID', self::LOCATION_ID)
            ->where('IsDynamic', 0)
            ->value('BinID');

        if ($locationBinId === null) {
            throw new \RuntimeException(
                "Location Bin not found for BinNumber: {$entry->location_code}"
            );
        }

        [$carton, $bin, $inventoryDetail] = DB::connection('portal')->transaction(function () use ($entry, $itemId): array {
            $carton = Carton::create([
                'CartonNumber' => $this->getNewCartonNumber(),
                'Position' => self::POSITION,
                'IsConfirmed' => true,
                'WarehouseID' => self::WAREHOUSE_ID,
                'ConfirmedBy' => self::CONFIRMED_BY,
                'ConfirmedOn' => now('UTC'),
            ]);

            $bin = Bin::create([
                'BinNumber' => $entry->location_code.'-'.$carton->CartonID,
                'LocationID' => self::LOCATION_ID,
                'IsDynamic' => 1,
                'CartonID' => $carton->CartonID,
                'CreatedOn' => now('UTC'),
            ]);

            CartonLineConfirm::create([
                'CartonID' => $carton->CartonID,
                'ItemID' => $itemId,
                'Quantity' => $entry->quantity,
            ]);

            $inventoryDetail = RealtimeInventoryDetail::create([
                'ItemId' => $itemId,
                'LocationId' => self::LOCATION_ID,
                'BinId' => $bin->BinID,
                'QtyOnHand' => $entry->quantity,
                'QtyAvailable' => $entry->quantity,
                'CreationTime' => now('UTC'),
                'LastModificationTime' => now('UTC'),
            ]);

            $this->markItemStockForSync((int) $itemId);

            return [$carton, $bin, $inventoryDetail];
        });

        DB::transaction(function () use ($entry, $carton, $bin, $itemId, $operationId, $inventoryDetail): void {
            StockGenerationLog::create([
                'operation_id' => $operationId,
                'entry_id' => $entry->id,
                'type' => $entry->type,
                'code' => $entry->code,
                'action' => 'created',
                'carton_id' => (int) $carton->CartonID,
                'bin_id' => (int) $bin->BinID,
                'item_id' => (int) $itemId,
                'inventory_detail_id' => $inventoryDetail->Id,
                'tpin' => $entry->code,
                'location_code' => $entry->location_code,
                'quantity' => (int) $entry->quantity,
                'qty_on_hand_before' => null,
                'qty_on_hand_after' => (int) $entry->quantity,
                'qty_available_before' => null,
                'qty_available_after' => (int) $entry->quantity,
            ]);

            $entry->carton_id = (int) $carton->CartonID;
            $entry->stock_generated = true;
            $entry->error = null;
            $entry->save();
        });
    }

    /**
     * Generate a unique carton number.
     */
    private function getNewCartonNumber(): string
    {
        do {
            $random = 'CTN'.strtoupper(Str::random(8));
        } while (Carton::where('CartonNumber', $random)->exists());

        return $random;
    }

    /**
     * Reset the stock sync flags for an item so its stock gets re-synced
     * to Bolton and Magento after an inventory change.
     */
    private function markItemStockForSync(int $itemId): void
    {
        ItemExtend::where('ItemId', $itemId)->update([
            'IsSyncStockToBolton' => 0,
            'IsSyncStockToMagento' => 0,
        ]);
    }
}
