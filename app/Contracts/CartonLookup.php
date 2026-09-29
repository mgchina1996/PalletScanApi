<?php

namespace App\Contracts;

interface CartonLookup
{
    /**
     * @param  list<string>  $recognizedValues
     * @return list<array{cartonID: int, cartonNumber: string, products: list<array{itemID: int, tpin: string}>}>
     */
    public function findByRecognizedValues(array $recognizedValues): array;
}
