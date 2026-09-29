<?php

namespace App\Services;

use App\Contracts\CartonLookup;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PortalCartonLookup implements CartonLookup
{
    /**
     * @param  list<string>  $recognizedValues
     * @return list<array{cartonID: int, cartonNumber: string, products: list<array{itemID: int, tpin: string}>}>
     */
    public function findByRecognizedValues(array $recognizedValues): array
    {
        $cartonNumbers = array_values(array_filter(
            $recognizedValues,
            fn (string $value): bool => str_starts_with($value, 'CTN'),
        ));
        $cartonIds = array_map(
            static fn (string $value): int => (int) $value,
            array_values(array_filter(
                $recognizedValues,
                fn (string $value): bool => ctype_digit($value) && in_array(strlen($value), [5, 6], true),
            )),
        );

        if ($cartonNumbers === [] && $cartonIds === []) {
            return [];
        }

        $rows = DB::connection('portal')
            ->table('Carton as c')
            ->join('CartonLineConfirm as clc', 'clc.CartonID', '=', 'c.CartonID')
            ->join('Item as i', 'i.ItemID', '=', 'clc.ItemID')
            ->where(function (Builder $query) use ($cartonNumbers, $cartonIds): void {
                if ($cartonNumbers !== []) {
                    $query->whereIn('c.CartonNumber', $cartonNumbers);
                }

                if ($cartonIds !== []) {
                    if ($cartonNumbers === []) {
                        $query->whereIn('c.CartonID', $cartonIds);
                    } else {
                        $query->orWhereIn('c.CartonID', $cartonIds);
                    }
                }
            })
            ->orderBy('c.CartonNumber')
            ->orderBy('i.TPIN')
            ->distinct()
            ->get([
                'c.CartonID as cartonID',
                'c.CartonNumber as cartonNumber',
                'i.ItemID as itemID',
                'i.TPIN as tpin',
            ]);

        return $rows
            ->groupBy('cartonID')
            ->map(function ($products): array {
                $carton = $products->first();

                return [
                    'cartonID' => (int) $carton->cartonID,
                    'cartonNumber' => (string) $carton->cartonNumber,
                    'products' => $products->map(fn ($product): array => [
                        'itemID' => (int) $product->itemID,
                        'tpin' => (string) $product->tpin,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
