<?php

namespace Rmirandasv\Wompi;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Rmirandasv\Wompi\Contracts\WompiClientInterface;
use Rmirandasv\Wompi\Http\Middleware\VerifyWompiWebhookSignature;

class WompiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/wompi.php', 'wompi'
        );

        $this->app->singleton(WompiClientInterface::class, function (): WompiClient {
            return WompiClient::fromConfig();
        });

        $this->app->alias(WompiClientInterface::class, WompiClient::class);
        $this->app->alias(WompiClientInterface::class, 'wompi');
    }

    public function boot(\Illuminate\Routing\Router $router): void
    {
        $router->aliasMiddleware('wompi.webhook', VerifyWompiWebhookSignature::class);

        $this->registerRateLimiter();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/wompi.php' => config_path('wompi.php'),
            ], 'wompi-config');

            $this->publishes([
                __DIR__.'/../routes/wompi.php' => base_path('routes/wompi.php'),
            ], 'wompi-webhook');

            $this->publishes([
                __DIR__.'/../stubs/WompiWebhookController.stub' => app_path('Http/Controllers/WompiWebhookController.php'),
            ], 'wompi-webhook');
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            WompiClientInterface::class,
            WompiClient::class,
            'wompi',
        ];
    }

    /**
     * A rate limiter for the webhook endpoint, so a misbehaving client cannot
     * exhaust the worker pool.
     */
    private function registerRateLimiter(): void
    {
        RateLimiter::for('wompi-webhook', function ($request) {
            $limit = (int) config('wompi.webhook.rate_limit', 120);

            return Limit::perMinute($limit)->by($request->ip());
        });
    }
}
