<?php

namespace App\Providers;

use App\Contracts\CartonTextRecognizer;
use App\Services\GoogleVisionCartonTextRecognizer;
use App\Services\OssImageStorage;
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

        $this->app->singleton(OssImageStorage::class, fn (): OssImageStorage => new OssImageStorage(
            accessKeyId: (string) config('services.aliyun_oss.access_key_id'),
            accessKeySecret: (string) config('services.aliyun_oss.access_key_secret'),
            endpoint: (string) config('services.aliyun_oss.endpoint'),
            bucket: (string) config('services.aliyun_oss.bucket'),
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
