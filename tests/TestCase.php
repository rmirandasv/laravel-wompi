<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Rmirandasv\Wompi\WompiServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            WompiServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default configuration
        $app['config']->set('wompi.auth_url', 'https://id.wompi.sv/test');
        $app['config']->set('wompi.api_url', 'https://api.wompi.sv/v1/test');
        $app['config']->set('wompi.client_id', 'test_client_id');
        $app['config']->set('wompi.client_secret', 'test_client_secret');
    }
}
