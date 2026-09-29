<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartonProductResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CartonProductController extends Controller
{
    public function __invoke(string $cartonNumber): AnonymousResourceCollection
    {
        $products = DB::connection('portal')
            ->table('Carton as c')
            ->join('CartonLineConfirm as clc', 'clc.CartonID', '=', 'c.CartonID')
            ->join('Item as i', 'i.ItemID', '=', 'clc.ItemID')
            ->where('c.CartonNumber', $cartonNumber)
            ->orderBy('i.TPIN')
            ->get([
                'c.CartonID as cartonID',
                'c.CartonNumber as cartonNumber',
                'i.TPIN as tpin',
            ]);

        return CartonProductResource::collection($products);
    }
}
