<?php

use App\Http\Controllers\ReportController;
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
