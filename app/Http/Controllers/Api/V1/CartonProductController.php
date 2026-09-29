<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FindCartonProductsRequest;
use App\Http\Resources\CartonProductResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CartonProductController extends Controller
{
    public function __invoke(FindCartonProductsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $query = DB::connection('portal')
            ->table('Carton as c')
            ->join('CartonLineConfirm as clc', 'clc.CartonID', '=', 'c.CartonID')
            ->join('Item as i', 'i.ItemID', '=', 'clc.ItemID');

        if (isset($validated['cartonNumber'])) {
            $query->where('c.CartonNumber', $validated['cartonNumber']);
        } else {
            $query->where('c.CartonID', (int) $validated['cartonID']);
        }

        $products = $query
            ->orderBy('i.TPIN')
            ->get([
                'c.CartonID as cartonID',
                'c.CartonNumber as cartonNumber',
                'i.TPIN as tpin',
            ]);

        if ($products->isEmpty()) {
            return CartonProductResource::collection([]);
        }

        $carton = $products->first();
        $cartons = collect([
            (object) [
                'cartonID' => (int) $carton->cartonID,
                'cartonNumber' => (string) $carton->cartonNumber,
                'products' => $products
                    ->unique('tpin')
                    ->map(static fn (object $product): array => [
                        'tpin' => (string) $product->tpin,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);

        return CartonProductResource::collection($cartons);
    }
}
