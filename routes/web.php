<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ReportController;
use App\Http\Middleware\AuthenticateAdmin;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\View\View;

Route::get('/', function (): View {
    return view('welcome');
})
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->name('pallet-scan');

Route::get('/report', ReportController::class)->name('report');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('login', [AdminController::class, 'login'])->name('login');
    Route::post('login', [AdminController::class, 'authenticate'])->middleware('throttle:5,1')->name('authenticate');
    Route::middleware(AuthenticateAdmin::class)->group(function (): void {
        Route::post('logout', [AdminController::class, 'logout'])->name('logout');
        Route::get('data/summary', [AdminController::class, 'summary'])->name('summary');
        Route::get('data/entries', [AdminController::class, 'entries'])->name('entries');
        Route::get('data/entries/{entry}', [AdminController::class, 'show'])->whereNumber('entry')->name('show');
        Route::get('data/entries/{entry}/image', [AdminController::class, 'image'])->whereNumber('entry')->name('image');
        Route::post('data/entries/{entry}/generate', [AdminController::class, 'generate'])->whereNumber('entry')->name('generate');
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('{section}', [AdminController::class, 'index'])->where('section', 'small-parce|ltl|exceptions')->name('section');
    });
});
