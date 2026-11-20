<?php

namespace App\Providers;

use App\Services\ResumeExtraction\HttpResumeExtractor;
use App\Services\ResumeExtraction\ResumeExtractor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
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
        // Public application endpoints (resume upload + submit). Each upload
        // costs an AI extraction, so keep strangers from flooding them.
        RateLimiter::for('applications', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        // $model->updated_at->display() → "2026/11/20 22:30" in Japan time.
        Carbon::macro('display', function (string $format = 'Y/m/d H:i'): string {
            /** @var Carbon $this */
            return $this->copy()->setTimezone(config('app.display_timezone'))->format($format);
        });
    }
}
