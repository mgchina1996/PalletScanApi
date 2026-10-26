<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\Portal\Bin;
use App\Models\Portal\Carton;
use App\Models\Portal\CartonLineConfirm;
use App\Models\Portal\CartonTransferLog;
use App\Models\Portal\Item;
use App\Models\Portal\ItemExtend;
use App\Models\Portal\RealtimeInventoryDetail;
use App\Models\StockGenerationLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class StockGenerationService
{
    private const LOCATION_ID = 22;

    public function __construct(private readonly PortalCartonCreator $cartonCreator) {}

    /**
     * Process a single entry and generate stock in the portal system.
     */
    public function generate(Entry $entry): void
    {
        $lock = Cache::lock('stock-entry:'.$entry->id, 600);
        if (! $lock->get()) {
            throw new \RuntimeException('此记录正在生成库存，请稍后刷新。');
        }

        try {
            $entry->refresh();
            if ($entry->stock_generated || $entry->is_deleted) {
                return;
            }
            $this->generateEntry($entry);
        } finally {
            $lock->release();
        }
    }

    private function generateEntry(Entry $entry): void
    {
        $operationId = Str::uuid()->toString();

        try {
            if (in_array(explode('-', $entry->location_code, 2)[0], ['N1', 'N2', 'N3', 'N4'], true) && $entry->type !== Entry::TYPE_CARTON) {
                throw new \RuntimeException('N1–N4 库位仅支持 Carton 类型。');
            }

            if ($entry->type === Entry::TYPE_CARTON) {
                $this->processCartonType($entry, $operationId);
            } elseif (in_array($entry->type, [Entry::TYPE_TPIN, Entry::TYPE_SKU], true)) {
                $this->processProductType($entry, $operationId);
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
     * all RealtimeInventoryDetail rows, clearing products omitted from the entry.
     */
    private function processCartonType(Entry $entry, string $operationId): void
    {
        $entry->load('products');
        $isLtl = in_array(explode('-', $entry->location_code, 2)[0], ['N1', 'N2', 'N3', 'N4'], true);

        if ($isLtl && $entry->products->isEmpty()) {
            throw new \RuntimeException('LTL Carton 没有录入商品明细，无法调整库存。');
        }

        $carton = Carton::where('CartonNumber', $entry->code)->first();
        if ($carton === null) {
            throw new \RuntimeException("Carton not found in portal for CartonNumber: {$entry->code}");
        }

        $itemIds = [];
        foreach ($entry->products as $product) {
            $itemId = Item::where('TPIN', $product->tpin)->value('ItemID');
            if ($itemId === null) {
                throw new \RuntimeException("Item not found in portal for TPIN: {$product->tpin}");
            }
            $itemIds[$product->tpin] = $itemId;
        }

        $logs = DB::connection('portal')->transaction(function () use ($entry, $carton, $itemIds, $isLtl): array {
            $binNumber = $entry->location_code.'-'.$carton->CartonID;
            if ($isLtl) {
                $bin = Bin::where('CartonID', $carton->CartonID)
                    ->where('IsDynamic', 1)
                    ->lockForUpdate()
                    ->first();

                if ($bin === null) {
                    throw new \RuntimeException("Dynamic Bin not found for CartonID: {$carton->CartonID}");
                }

                if ((int) $bin->LocationID !== self::LOCATION_ID) {
                    throw new \RuntimeException("Carton Bin LocationID must be 22, actual: {$bin->LocationID}");
                }

                $fromBinNumber = $bin->BinNumber;
                if ($fromBinNumber !== $binNumber) {
                    $bin->update(['BinNumber' => $binNumber]);

                    CartonTransferLog::create([
                        'BinID' => $bin->BinID,
                        'FromBinNumber' => $fromBinNumber,
                        'CartonID' => $bin->CartonID,
                        'LocationID' => $bin->LocationID,
                        'ToBinNumber' => $binNumber,
                        'CreatedBy' => 588,
                        'CreatedOn' => now('UTC'),
                    ]);
                }
            } else {
                $bin = Bin::where('CartonID', $carton->CartonID)->first();
            }

            if ($bin === null) {
                $bin = Bin::create([
                    'BinNumber' => $binNumber,
                    'LocationID' => self::LOCATION_ID,
                    'IsDynamic' => 1,
                    'CartonID' => $carton->CartonID,
                    'CreatedOn' => now('UTC'),
                ]);
            } elseif ($bin->BinNumber !== $binNumber) {
                $bin->update([
                    'BinNumber' => $binNumber,
                ]);
            }

            $inventoryDetails = RealtimeInventoryDetail::query()
                ->join('Bin as b', 'b.BinID', '=', 'portal_realtime_inventory_detail.BinId')
                ->join('Carton as c', 'c.CartonID', '=', 'b.CartonID')
                ->join('Item as i', 'i.ItemID', '=', 'portal_realtime_inventory_detail.ItemId')
                ->where('c.CartonNumber', $entry->code)
                ->select('portal_realtime_inventory_detail.*', 'i.TPIN')
                ->lockForUpdate()
                ->get();

            $productsByItemId = [];
            foreach ($entry->products as $product) {
                $productsByItemId[$itemIds[$product->tpin]] = $product;
            }

            $adjustments = [];
            foreach ($inventoryDetails as $detail) {
                $product = $productsByItemId[$detail->ItemId] ?? null;
                $adjustments[] = [
                    'detail' => $detail,
                    'item_id' => (int) $detail->ItemId,
                    'tpin' => (string) $detail->TPIN,
                    'quantity' => $product !== null ? (int) $product->quantity : 0,
                ];
            }

            foreach ($productsByItemId as $itemId => $product) {
                if ($inventoryDetails->contains('ItemId', $itemId)) {
                    continue;
                }

                if ($isLtl) {
                    throw new \RuntimeException("Inventory not found for CartonID: {$carton->CartonID}, TPIN: {$product->tpin}");
                }

                $adjustments[] = [
                    'detail' => null,
                    'item_id' => (int) $itemId,
                    'tpin' => $product->tpin,
                    'quantity' => (int) $product->quantity,
                ];
            }

            $records = [];
            foreach ($adjustments as $adjustment) {
                $detail = $adjustment['detail'];
                $itemId = $adjustment['item_id'];
                $quantity = $adjustment['quantity'];

                if ($detail === null) {
                    $detail = RealtimeInventoryDetail::create([
                        'ItemId' => $itemId,
                        'LocationId' => self::LOCATION_ID,
                        'BinId' => $bin->BinID,
                        'QtyOnHand' => $quantity,
                        'QtyAvailable' => $quantity,
                        'CreationTime' => now('UTC'),
                        'LastModificationTime' => now('UTC'),
                    ]);
                    $beforeOnHand = null;
                    $beforeAvailable = null;
                    $action = 'created';
                } else {
                    $beforeOnHand = $detail->QtyOnHand;
                    $beforeAvailable = $detail->QtyAvailable;

                    $detail->update([
                        'QtyOnHand' => $quantity,
                        'QtyAvailable' => $quantity,
                        'LastModificationTime' => now('UTC'),
                    ]);
                    $action = 'updated';
                }

                $this->markItemStockForSync($itemId);

                $records[] = [
                    'operation_id' => null,
                    'entry_id' => $entry->id,
                    'type' => $entry->type,
                    'code' => $entry->code,
                    'action' => $action,
                    'carton_id' => (int) $bin->CartonID,
                    'bin_id' => (int) $detail->BinId,
                    'item_id' => (int) $itemId,
                    'inventory_detail_id' => $detail->Id,
                    'tpin' => $adjustment['tpin'],
                    'location_code' => $entry->location_code,
                    'quantity' => $quantity,
                    'qty_on_hand_before' => $beforeOnHand !== null ? (int) $beforeOnHand : null,
                    'qty_on_hand_after' => (int) $quantity,
                    'qty_available_before' => $beforeAvailable !== null ? (int) $beforeAvailable : null,
                    'qty_available_after' => (int) $quantity,
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
     * TPIN and SKU types: reuse their carton, then generate inventory.
     */
    private function processProductType(Entry $entry, string $operationId): void
    {
        $lookupColumn = $entry->type === Entry::TYPE_SKU ? 'SKU' : 'TPIN';
        $item = Item::query()->where($lookupColumn, $entry->code)->first(['ItemID', 'TPIN']);

        if ($item === null) {
            throw new \RuntimeException("Item not found in portal for {$lookupColumn}: {$entry->code}");
        }

        $itemId = (int) $item->ItemID;

        $locationBinId = Bin::where('BinNumber', $entry->location_code)
            ->where('LocationID', self::LOCATION_ID)
            ->where('IsDynamic', 0)
            ->value('BinID');

        if ($locationBinId === null) {
            throw new \RuntimeException(
                "Location Bin not found for BinNumber: {$entry->location_code}"
            );
        }

        $carton = $entry->carton_id !== null
            ? Carton::query()->find($entry->carton_id)
            : $this->cartonCreator->create();

        if ($carton === null) {
            throw new \RuntimeException("Carton not found in portal for CartonID: {$entry->carton_id}");
        }

        [$bin, $inventoryDetail] = DB::connection('portal')->transaction(function () use ($entry, $itemId, $carton): array {

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

            return [$bin, $inventoryDetail];
        });

        DB::transaction(function () use ($entry, $carton, $bin, $item, $itemId, $operationId, $inventoryDetail): void {
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
                'tpin' => (string) $item->TPIN,
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
