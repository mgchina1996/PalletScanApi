<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryOverviewRequest;
use App\Models\Entry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class InventoryOverviewController extends Controller
{
    private const TIMEZONE = 'America/Los_Angeles';

    public function __invoke(InventoryOverviewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $allDates = (bool) ($validated['allDates'] ?? false);
        $today = CarbonImmutable::now(self::TIMEZONE)->toDateString();
        $dateFrom = $validated['dateFrom'] ?? $validated['date'] ?? $today;
        $dateTo = $validated['dateTo'] ?? $dateFrom;
        $baseQuery = Entry::query();

        if (! $allDates) {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $dateFrom, self::TIMEZONE)->utc();
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $dateTo, self::TIMEZONE)->addDay()->utc();
            $baseQuery
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $end);
        }

        $entered = (clone $baseQuery)->count();
        $stocked = (clone $baseQuery)->where('stock_generated', true)->count();
        $notStocked = $entered - $stocked;

        $locations = (clone $baseQuery)
            ->select('location_code')
            ->selectRaw('COUNT(*) as entered_count')
            ->selectRaw('SUM(CASE WHEN stock_generated = 1 THEN 1 ELSE 0 END) as stocked_count')
            ->selectRaw('SUM(CASE WHEN stock_generated = 0 THEN 1 ELSE 0 END) as not_stocked_count')
            ->selectRaw('MAX(created_at) as last_entry_at')
            ->groupBy('location_code')
            ->orderByDesc('entered_count')
            ->orderBy('location_code')
            ->get()
            ->map(static function (Entry $entry): array {
                $lastEntryAt = $entry->getAttribute('last_entry_at');

                return [
                    'locationCode' => (string) $entry->location_code,
                    'entered' => (int) $entry->getAttribute('entered_count'),
                    'stocked' => (int) $entry->getAttribute('stocked_count'),
                    'notStocked' => (int) $entry->getAttribute('not_stocked_count'),
                    'lastEntryAt' => $lastEntryAt !== null
                        ? CarbonImmutable::parse($lastEntryAt, 'UTC')->setTimezone(self::TIMEZONE)->format('Y-m-d H:i')
                        : null,
                ];
            });

        return response()->json([
            'summary' => [
                'entered' => $entered,
                'locations' => $locations->count(),
                'stocked' => $stocked,
                'notStocked' => $notStocked,
            ],
            'locations' => $locations,
            'dateFrom' => $allDates ? null : $dateFrom,
            'dateTo' => $allDates ? null : $dateTo,
            'allDates' => $allDates,
            'timezone' => self::TIMEZONE,
        ]);
    }
}
