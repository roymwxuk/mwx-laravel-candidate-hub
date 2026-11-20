<?php

namespace App\Providers;

use App\Services\ResumeExtraction\HttpResumeExtractor;
use App\Services\ResumeExtraction\ResumeExtractor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ResumeExtractor::class, fn () => new HttpResumeExtractor(
            baseUrl: (string) config('services.extractor.url'),
            timeout: (int) config('services.extractor.timeout', 60),
            token: config('services.extractor.token'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
