<?php

namespace App\Providers;

use App\Contracts\CartonTextRecognizer;
use App\Services\GoogleVisionCartonTextRecognizer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CartonTextRecognizer::class, fn (): GoogleVisionCartonTextRecognizer => new GoogleVisionCartonTextRecognizer(
            credentialsPath: (string) config('services.google_vision.credentials'),
        ));

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
