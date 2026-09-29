<?php

namespace App\Providers;

use App\Contracts\CartonLookup;
use App\Contracts\OcrRecognizer;
use App\Services\AlibabaCloudOcrRecognizer;
use App\Services\PortalCartonLookup;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CartonLookup::class, PortalCartonLookup::class);

        $this->app->singleton(OcrRecognizer::class, fn (): AlibabaCloudOcrRecognizer => new AlibabaCloudOcrRecognizer(
            accessKeyId: (string) config('services.alibaba_cloud.access_key_id'),
            accessKeySecret: (string) config('services.alibaba_cloud.access_key_secret'),
            endpoint: (string) config('services.alibaba_cloud.ocr_endpoint'),
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
