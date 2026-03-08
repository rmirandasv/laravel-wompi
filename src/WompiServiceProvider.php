<?php

namespace Rmirandasv\Wompi;

use Illuminate\Support\ServiceProvider;

class WompiServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/wompi.php', 'wompi'
        );

        $this->app->singleton(\Rmirandasv\Wompi\Contracts\WompiClientInterface::class, function ($app) {
            return new WompiClient(
                $app['config']['wompi.auth_url'],
                $app['config']['wompi.api_url'],
                $app['config']['wompi.client_id'],
                $app['config']['wompi.client_secret'],
            );
        });

        $this->app->alias(\Rmirandasv\Wompi\Contracts\WompiClientInterface::class, WompiClient::class);
        $this->app->alias(\Rmirandasv\Wompi\Contracts\WompiClientInterface::class, 'wompi');
    }

    public function boot(\Illuminate\Routing\Router $router)
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/wompi.php' => config_path('wompi.php'),
            ], 'wompi-config');
        }

        $router->aliasMiddleware('wompi.webhook', \Rmirandasv\Wompi\Http\Middleware\VerifyWompiWebhookSignature::class);
    }
}
