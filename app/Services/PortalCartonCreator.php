<?php

namespace App\Services;

use App\Models\Portal\Carton;
use Illuminate\Support\Str;

class PortalCartonCreator
{
    private const WAREHOUSE_ID = 3;

    private const CONFIRMED_BY = 491;

    private const POSITION = 1;

    public function create(): Carton
    {
        return Carton::create([
            'CartonNumber' => $this->getNewCartonNumber(),
            'Position' => self::POSITION,
            'IsConfirmed' => true,
            'WarehouseID' => self::WAREHOUSE_ID,
            'ConfirmedBy' => self::CONFIRMED_BY,
            'ConfirmedOn' => now('UTC'),
        ]);
    }

    private function getNewCartonNumber(): string
    {
        do {
            $cartonNumber = 'CTN'.strtoupper(Str::random(8));
        } while (Carton::where('CartonNumber', $cartonNumber)->exists());

        return $cartonNumber;
    }
}
