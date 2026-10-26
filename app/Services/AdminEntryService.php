<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminEntryService
{
    /** @return array<string, array{qtyOnHand: float, qtyAvailable: float}> */
    public function cartonInventory(Entry $entry): array
    {
        if ($entry->type !== Entry::TYPE_CARTON || $entry->products->isEmpty()) {
            return [];
        }

        return DB::connection('portal')->table('Carton as c')
            ->join('Bin as b', 'b.CartonID', '=', 'c.CartonID')
            ->join('portal_realtime_inventory_detail as pd', 'pd.BinId', '=', 'b.BinID')
            ->join('Item as i', 'i.ItemID', '=', 'pd.ItemId')
            ->where('c.CartonNumber', $entry->code)
            ->whereIn('i.TPIN', $entry->products->pluck('tpin'))
            ->select('i.TPIN')
            ->selectRaw('SUM(pd.QtyOnHand) as qty_on_hand, SUM(pd.QtyAvailable) as qty_available')
            ->groupBy('i.TPIN')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [
                (string) $row->TPIN => [
                    'qtyOnHand' => (float) $row->qty_on_hand,
                    'qtyAvailable' => (float) $row->qty_available,
                ],
            ])->all();
    }

    /** @return list<string> */
    public function prefixes(string $zone): array
    {
        return $zone === 'small-parce' ? ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'] : ['N1', 'N2', 'N3', 'N4'];
    }

    /** @return Builder<Entry> */
    public function query(string $zone): Builder
    {
        $query = Entry::query()->where('is_deleted', false);

        if ($zone === 'exceptions') {
            return $query->whereNot(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $this->scopeZone($query, 'small-parce');
                })->orWhere(function (Builder $query): void {
                    $this->scopeZone($query, 'ltl');
                });
            });
        }

        $this->scopeZone($query, $zone);

        return $query;
    }

    /** @param Builder<Entry> $query */
    private function scopeZone(Builder $query, string $zone): void
    {
        $query->where(function (Builder $query) use ($zone): void {
            foreach ($this->prefixes($zone) as $prefix) {
                $query->orWhere('location_code', 'like', $prefix.'-%');
            }
        })->whereIn('type', $zone === 'ltl' ? [Entry::TYPE_CARTON] : [Entry::TYPE_CARTON, Entry::TYPE_SKU, Entry::TYPE_TPIN]);
    }

    /** @param Builder<Entry> $query */
    public function scopeStatus(Builder $query, string $status): void
    {
        if ($status === 'success') {
            $query->where('stock_generated', true);
        } elseif (in_array($status, ['pending', 'failed'], true)) {
            $query->where('stock_generated', false)->where(function (Builder $query) use ($status): void {
                if ($status === 'pending') {
                    $query->whereNull('error')->orWhere('error', '');
                } else {
                    $query->whereNotNull('error')->where('error', '!=', '');
                }
            });
        }
    }

    public function status(Entry $entry): string
    {
        return $entry->stock_generated ? 'success' : (($entry->error === null || $entry->error === '') ? 'pending' : 'failed');
    }

    /** @return array<string, array<string, int>|int> */
    public function summary(): array
    {
        $summary = [];
        foreach (['small-parce', 'ltl'] as $zone) {
            foreach (['pending', 'failed', 'success'] as $status) {
                $query = $this->query($zone);
                $this->scopeStatus($query, $status);
                $summary[$zone][$status] = $query->count();
            }
        }
        $summary['exceptions'] = $this->query('exceptions')->count();

        return $summary;
    }
}
