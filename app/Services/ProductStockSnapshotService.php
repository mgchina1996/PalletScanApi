<?php

namespace App\Services;

use App\Models\ProductStockSnapshot;
use App\Models\StockGenerationLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ProductStockSnapshotService
{
    /**
     * Rebuild the product stock snapshot for the given stock generation date.
     */
    public function create(CarbonInterface $date): int
    {
        $snapshotDate = $date->toDateString();
        $itemIds = StockGenerationLog::query()
            ->whereDate('created_at', $snapshotDate)
            ->whereNotNull('item_id')
            ->distinct()
            ->orderBy('item_id')
            ->pluck('item_id');

        return DB::transaction(function () use ($itemIds, $snapshotDate): int {
            ProductStockSnapshot::query()->whereDate('ss_date', $snapshotDate)->delete();

            $inserted = 0;

            foreach ($itemIds->chunk(1000) as $itemIdChunk) {
                $rows = $this->getPortalRows($itemIdChunk->all(), $snapshotDate);

                foreach (array_chunk($rows, 500) as $rowChunk) {
                    ProductStockSnapshot::query()->insert($rowChunk);
                    $inserted += count($rowChunk);
                }
            }

            return $inserted;
        });
    }

    /**
     * @param  list<int>  $itemIds
     * @return list<array<string, bool|float|int|string|null>>
     */
    private function getPortalRows(array $itemIds, string $snapshotDate): array
    {
        if ($itemIds === []) {
            return [];
        }

        return DB::connection('portal')
            ->table('Item as i')
            ->join('Vendor as v', 'v.VendorID', '=', 'i.VendorID')
            ->leftJoin('ItemSelection as isl', 'isl.ItemID', '=', 'i.ItemID')
            ->leftJoin('ItemOption as io', 'io.ItemOptionID', '=', 'isl.ItemOptionID')
            ->leftJoin('Item as pl', 'pl.ItemID', '=', 'io.ItemID')
            ->whereIn('i.ItemID', $itemIds)
            ->select([
                'i.ItemID as product_id',
                'v.MerchantID as merchant_id',
                'i.TPIN as tpin',
                'i.SKU as sku',
                'i.Approval as approval',
                'i.Status as status',
                'i.Visibility as visibility',
                'i.Price as price',
                'i.SpecialPrice as special_price',
                'pl.TPIN as bundle_parent_tpin',
            ])
            ->selectRaw('CASE WHEN EXISTS (SELECT 1 FROM ItemImage img WHERE img.ItemID = i.ItemID) THEN 1 ELSE 0 END as has_image')
            ->selectRaw('CASE WHEN pl.ItemID IS NULL THEN 0 ELSE 1 END as is_bundle_child')
            ->selectRaw("CASE WHEN pl.ItemID IS NULL THEN NULL WHEN pl.Approval = 'Approved' THEN 1 ELSE 0 END as bundle_parent_visible")
            ->selectRaw('CASE WHEN i.IsPartItem IS NULL THEN 0 ELSE i.IsPartItem END as is_parts')
            ->selectRaw('COALESCE((SELECT SUM(rid.QtyAvailable) FROM portal_realtime_inventory_detail rid WHERE rid.LocationID IN (SELECT LocationID FROM Location WHERE IsSellable = 1 AND IsRMA = 0 AND IsRefurbished = 0) AND rid.ItemID = i.ItemID), 0) as qty_available')
            ->distinct()
            ->orderBy('i.ItemID')
            ->orderBy('pl.TPIN')
            ->get()
            ->map(static fn (object $row): array => [
                'product_id' => (int) $row->product_id,
                'merchant_id' => (string) $row->merchant_id,
                'tpin' => $row->tpin,
                'sku' => $row->sku,
                'approval' => $row->approval,
                'status' => $row->status !== null ? (int) $row->status : null,
                'visibility' => $row->visibility !== null ? (int) $row->visibility : null,
                'price' => $row->price,
                'special_price' => $row->special_price,
                'has_image' => (bool) $row->has_image,
                'is_bundle_child' => (bool) $row->is_bundle_child,
                'bundle_parent_tpin' => $row->bundle_parent_tpin,
                'bundle_parent_visible' => $row->bundle_parent_visible !== null
                    ? (bool) $row->bundle_parent_visible
                    : null,
                'is_parts' => (bool) $row->is_parts,
                'qty_available' => $row->qty_available,
                'ss_date' => $snapshotDate,
            ])
            ->all();
    }
}
