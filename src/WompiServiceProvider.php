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

        $this->app->singleton(WompiClient::class, function ($app) {
            return new WompiClient(
                $app['config']['wompi.auth_url'],
                $app['config']['wompi.api_url'],
                $app['config']['wompi.client_id'],
                $app['config']['wompi.client_secret'],
            );
        });

        $this->app->alias(WompiClient::class, 'wompi');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/wompi.php' => config_path('wompi.php'),
            ], 'wompi-config');
        }
    }
}
