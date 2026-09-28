<?php

namespace App\Providers;

use App\Jobs\SendInvoiceMailing;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        JsonResource::withoutWrapping();

        RateLimiter::for(SendInvoiceMailing::RATE_LIMITER, function () {
            return Limit::perSecond(SendInvoiceMailing::sendsPerSecond())
                ->by(SendInvoiceMailing::RATE_LIMITER);
        });
    }
}
