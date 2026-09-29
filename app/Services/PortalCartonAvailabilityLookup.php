<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PortalCartonAvailabilityLookup
{
    /**
     * @param  list<int>  $cartonIds
     * @param  list<string>  $cartonNumbers
     * @return list<array{cartonID: int, cartonNumber: string}>
     */
    public function find(array $cartonIds, array $cartonNumbers): array
    {
        if ($cartonIds === [] && $cartonNumbers === []) {
            return [];
        }

        return DB::connection('portal')
            ->table('Carton')
            ->where(function (Builder $query) use ($cartonIds, $cartonNumbers): void {
                if ($cartonNumbers !== []) {
                    $query->whereIn('CartonNumber', $cartonNumbers);
                }

                if ($cartonIds !== []) {
                    if ($cartonNumbers === []) {
                        $query->whereIn('CartonID', $cartonIds);
                    } else {
                        $query->orWhereIn('CartonID', $cartonIds);
                    }
                }
            })
            ->orderBy('CartonNumber')
            ->get(['CartonID as cartonID', 'CartonNumber as cartonNumber'])
            ->map(static fn (object $carton): array => [
                'cartonID' => (int) $carton->cartonID,
                'cartonNumber' => (string) $carton->cartonNumber,
            ])
            ->all();
    }
}
