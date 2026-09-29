<?php

use App\Http\Controllers\Api\V1\CartonEntryController;
use App\Http\Controllers\Api\V1\CartonOcrController;
use App\Http\Controllers\Api\V1\CartonProductController;
use App\Http\Controllers\Api\V1\EntryController;
use App\Http\Controllers\Api\V1\EntryDetailController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\ProductEntryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('ocr/carton', CartonOcrController::class)
        ->middleware('throttle:10,1')
        ->name('ocr.carton');
    Route::get('locations', LocationController::class)->name('locations.index');
    Route::get('cartons/products', CartonProductController::class)
        ->name('cartons.products.index');
    Route::get('entries', EntryController::class)->name('entries.index');
    Route::get('entries/{entry}', EntryDetailController::class)
        ->whereNumber('entry')
        ->name('entries.show');
    Route::post('entries/cartons', [CartonEntryController::class, 'store'])
        ->name('entries.cartons.store');
    Route::post('entries/products/{type}', [ProductEntryController::class, 'store'])
        ->where('type', 'tpin|sku')
        ->name('entries.products.store');
});
