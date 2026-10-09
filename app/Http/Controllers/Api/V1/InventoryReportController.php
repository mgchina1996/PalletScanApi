<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryReportRequest;
use App\Models\Portal\Carton;
use App\Models\Portal\Item;
use App\Models\Portal\RealtimeInventoryDetail;
use App\Models\StockGenerationLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller
{
    private const TIMEZONE = 'America/Los_Angeles';

    private const PRODUCT_IMAGE_BASE_URL = 'https://content.toolots.com/media/catalog/product/';

    public function __invoke(InventoryReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = StockGenerationLog::query();

        $location = trim((string) ($validated['location'] ?? ''));
        if ($location !== '') {
            $query->where('location_code', 'like', "%{$location}%");
        }

        $allDates = (bool) ($validated['allDates'] ?? false);
        if (! $allDates) {
            $today = CarbonImmutable::now(self::TIMEZONE)->toDateString();
            $dateFrom = $validated['dateFrom'] ?? $validated['date'] ?? $today;
            $dateTo = $validated['dateTo'] ?? $dateFrom;
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $dateFrom, self::TIMEZONE)->utc();
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $dateTo, self::TIMEZONE)->addDay()->utc();
            $query->where('created_at', '>=', $start)->where('created_at', '<', $end);
        }

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $portalItemIds = Item::query()
                ->where('TPIN', 'like', "%{$search}%")
                ->orWhere('SKU', 'like', "%{$search}%")
                ->limit(500)
                ->pluck('ItemID')
                ->all();
            $portalCartonIds = Carton::query()
                ->where('CartonNumber', 'like', "%{$search}%")
                ->limit(500)
                ->pluck('CartonID')
                ->all();

            $query->where(function (Builder $query) use ($portalCartonIds, $portalItemIds, $search): void {
                $query
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('tpin', 'like', "%{$search}%");

                if ($portalItemIds !== []) {
                    $query->orWhereIn('item_id', $portalItemIds);
                }
                if ($portalCartonIds !== []) {
                    $query->orWhereIn('carton_id', $portalCartonIds);
                }
            });
        }

        $logs = $query
            ->select([
                'id',
                'entry_id',
                'type',
                'code',
                'action',
                'carton_id',
                'item_id',
                'inventory_detail_id',
                'tpin',
                'location_code',
                'quantity',
                'created_at',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($validated['perPage'] ?? 25));

        $collection = $logs->getCollection();
        $itemIds = $collection->pluck('item_id')->filter()->unique()->values();
        $cartonIds = $collection->pluck('carton_id')->filter()->unique()->values();
        $inventoryDetailIds = $collection->pluck('inventory_detail_id')->filter()->unique()->values();

        $items = $itemIds->isEmpty()
            ? collect()
            : Item::query()
                ->whereIn('ItemID', $itemIds)
                ->get(['ItemID', 'TPIN', 'SKU', 'Name'])
                ->keyBy('ItemID');
        $cartons = $cartonIds->isEmpty()
            ? collect()
            : Carton::query()
                ->whereIn('CartonID', $cartonIds)
                ->get(['CartonID', 'CartonNumber'])
                ->keyBy('CartonID');
        $inventoryDetails = $inventoryDetailIds->isEmpty()
            ? collect()
            : RealtimeInventoryDetail::query()
                ->whereIn('Id', $inventoryDetailIds)
                ->get(['Id', 'QtyOnHand', 'QtyAvailable'])
                ->keyBy(static fn (RealtimeInventoryDetail $detail): string => strtolower((string) $detail->Id));
        $imageUrls = $itemIds->isEmpty()
            ? collect()
            : DB::connection('portal')
                ->table('ItemImage')
                ->whereIn('ItemID', $itemIds)
                ->where('IsThumbnail', 1)
                ->where('Remove', 0)
                ->where('Exclude', 0)
                ->whereNotNull('FilePath')
                ->orderByDesc('IsBaseImage')
                ->orderBy('Position')
                ->orderBy('ItemImageID')
                ->get(['ItemID', 'FilePath'])
                ->unique('ItemID')
                ->mapWithKeys(static fn (object $image): array => [
                    (int) $image->ItemID => self::PRODUCT_IMAGE_BASE_URL.ltrim((string) $image->FilePath, '/'),
                ]);

        $rows = $collection->map(function (StockGenerationLog $log) use ($cartons, $imageUrls, $inventoryDetails, $items): array {
            $item = $log->item_id !== null ? $items->get($log->item_id) : null;
            $carton = $log->carton_id !== null ? $cartons->get($log->carton_id) : null;
            $inventory = $log->inventory_detail_id !== null
                ? $inventoryDetails->get(strtolower((string) $log->inventory_detail_id))
                : null;

            return [
                'id' => (int) $log->id,
                'entryId' => (int) $log->entry_id,
                'locationCode' => (string) $log->location_code,
                'type' => (string) $log->type,
                'action' => (string) $log->action,
                'itemId' => $item !== null ? (int) $item->ItemID : ($log->item_id !== null ? (int) $log->item_id : null),
                'tpin' => $item !== null ? (string) $item->TPIN : ($log->tpin !== null ? (string) $log->tpin : null),
                'sku' => $item !== null ? (string) $item->SKU : null,
                'name' => $item !== null ? (string) $item->Name : null,
                'imageUrl' => $item !== null ? $imageUrls->get((int) $item->ItemID) : null,
                'cartonId' => $log->carton_id !== null ? (int) $log->carton_id : null,
                'cartonNumber' => $carton !== null ? (string) $carton->CartonNumber : null,
                'quantityOnHand' => $inventory !== null ? (int) $inventory->QtyOnHand : null,
                'quantityAvailable' => $inventory !== null ? (int) $inventory->QtyAvailable : null,
                'quantityScanned' => (int) $log->quantity,
                'stockedOn' => $log->created_at?->copy()->setTimezone(self::TIMEZONE)->format('Y-m-d'),
            ];
        });

        return response()->json([
            'data' => $rows,
            'locations' => StockGenerationLog::query()->distinct()->orderBy('location_code')->pluck('location_code'),
            'meta' => [
                'currentPage' => $logs->currentPage(),
                'lastPage' => $logs->lastPage(),
                'perPage' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
            'timezone' => self::TIMEZONE,
        ]);
    }
}
