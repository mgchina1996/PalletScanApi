<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LocationResource;
use App\Models\Portal\Bin;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $locations = Cache::rememberForever('pallet-locations:v1', function (): array {
            $locations = [];

            for ($section = 1; $section <= 6; $section++) {
                for ($aisle = 1; $aisle <= 15; $aisle++) {
                    foreach (range('A', 'E') as $level) {
                        for ($position = 1; $position <= 2; $position++) {
                            $locations[] = "S{$section}-A{$aisle}-{$level}{$position}";
                        }
                    }
                }
            }

            $portalBins = Bin::where(function ($query) {
                $query->where('BinNumber', 'LIKE', 'N1%')
                    ->orWhere('BinNumber', 'LIKE', 'N2%')
                    ->orWhere('BinNumber', 'LIKE', 'N3%');
            })
                ->where('IsDynamic', 0)
                ->where('LocationID', 22)
                ->pluck('BinNumber')
                ->all();

            $locations = array_merge($locations, $portalBins);

            return $locations;
        });

        return LocationResource::collection($locations);
    }
}
