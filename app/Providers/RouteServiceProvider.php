<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    protected function configureRateLimiting(): void
    {
        /**
         * Auth limiter — brute-force login protection.
         * Keyed by IP to prevent credential stuffing.
         * Default: 10 req/min (configurable via AUTH_RATE_LIMIT).
         */
        RateLimiter::for('auth', function (Request $request) {
            $limit = (int) config('app.rate_limits.auth', 10);
            return Limit::perMinute($limit)->by($request->ip());
        });

        /**
         * Products limiter — public listing/search.
         * Default: 60 req/min (configurable via PRODUCT_RATE_LIMIT).
         */
        RateLimiter::for('products', function (Request $request) {
            $limit = (int) config('app.rate_limits.products', 60);
            return Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip());
        });

        /**
         * Orders limiter — authenticated order creation.
         * Keyed by user ID to prevent order flooding.
         * Default: 20 req/min (configurable via ORDER_RATE_LIMIT).
         */
        RateLimiter::for('orders', function (Request $request) {
            $limit = (int) config('app.rate_limits.orders', 20);
            return Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip());
        });
    }
}
