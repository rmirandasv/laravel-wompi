<?php

namespace Tests;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Rmirandasv\Wompi\WompiServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * The client id used by the tests.
     */
    public const CLIENT_ID = 'test_client_id';

    /**
     * The client secret used by the tests.
     */
    public const CLIENT_SECRET = 'test_client_secret';

    protected function setUp(): void
    {
        parent::setUp();

        // A fake that does not match the request would otherwise perform the
        // real HTTP call against api.wompi.sv. The suite must stay offline.
        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app)
    {
        return [
            WompiServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');

        // The real Wompi base URLs are used on purpose: the requests are
        // faked, but the paths must match the ones Wompi documents.
        $app['config']->set('wompi.auth_url', 'https://id.wompi.sv/connect/token');
        $app['config']->set('wompi.api_url', 'https://api.wompi.sv');
        $app['config']->set('wompi.audience', 'wompi_api');
        $app['config']->set('wompi.client_id', self::CLIENT_ID);
        $app['config']->set('wompi.client_secret', self::CLIENT_SECRET);
    }

    /**
     * Fake the OAuth2 token endpoint.
     */
    protected function fakeAuthentication(string $token = 'test_token'): void
    {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => $token,
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'scope' => 'wompi_api',
            ], 200),
        ]);
    }

    /**
     * Fake the OAuth2 token endpoint and the given API paths.
     *
     * @param  array<string, mixed>  $responses
     */
    protected function fakeWompi(array $responses, string $token = 'test_token'): void
    {
        Http::fake(array_merge([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => $token,
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'scope' => 'wompi_api',
            ], 200),
        ], $responses));
    }
}
