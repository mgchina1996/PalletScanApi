<?php

use App\Http\Controllers\Api\V1\CartonEntryController;
use App\Http\Controllers\Api\V1\CartonOcrController;
use App\Http\Controllers\Api\V1\CartonProductController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\ProductEntryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('ocr/carton', CartonOcrController::class)
        ->middleware('throttle:10,1')
        ->name('ocr.carton');
    Route::get('locations', LocationController::class)->name('locations.index');
    Route::get('cartons/{cartonNumber}/products', CartonProductController::class)
        ->name('cartons.products.index');
    Route::post('locations/{locationCode}/cartons', [CartonEntryController::class, 'store'])
        ->where('locationCode', 'S[1-6]-A(?:[1-9]|1[0-5])-[A-E][12]')
        ->name('locations.cartons.store');
    Route::post('locations/{locationCode}/products/{type}', [ProductEntryController::class, 'store'])
        ->where([
            'locationCode' => 'S[1-6]-A(?:[1-9]|1[0-5])-[A-E][12]',
            'type' => 'tpin|sku',
        ])
        ->name('locations.products.store');
});
