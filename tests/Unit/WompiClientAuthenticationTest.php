<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\Contracts\WompiClientInterface;
use Rmirandasv\Wompi\Exceptions\ConfigurationException;
use Rmirandasv\Wompi\Exceptions\WompiApiException;
use Rmirandasv\Wompi\Exceptions\WompiAuthenticationException;
use Rmirandasv\Wompi\WompiClient;

describe('WompiClient authentication', function () {
    beforeEach(function () {
        Cache::flush();
    });

    it('throws when the credentials are missing', function () {
        new WompiClient(
            apiUrl: 'https://api.wompi.sv',
            authUrl: 'https://id.wompi.sv/connect/token',
            audience: 'wompi_api',
            clientId: null,
            clientSecret: null,
        );
    })->throws(ConfigurationException::class, 'Wompi credentials are not set');

    it('performs the client_credentials handshake', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_access_token_123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://api.wompi.sv/*' => Http::response([], 200),
        ]);

        app(WompiClientInterface::class)->getAplicativoData();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://id.wompi.sv/connect/token'
                && $request['grant_type'] === 'client_credentials'
                && $request['client_id'] === 'test_client_id'
                && $request['client_secret'] === 'test_client_secret'
                && $request['audience'] === 'wompi_api';
        });
    });

    it('requests the token only once and reuses the cache', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'cached_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::response([], 200),
        ]);

        $client = app(WompiClientInterface::class);

        $client->getAplicativoData();
        $client->getAplicativoData();
        $client->getAplicativoData();

        // One authentication request plus three API calls.
        Http::assertSentCount(4);

        expect(wompi_count_sent(fn ($request) => str_contains($request->url(), 'id.wompi.sv')))->toBe(1);
    });

    it('namespaces the cache key by client id', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'cached_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::response([], 200),
        ]);

        app(WompiClientInterface::class)->getAplicativoData();

        $prefix = config('wompi.token_cache.prefix');

        expect(Cache::get($prefix.':'.sha1('test_client_id')))->toBe('cached_token');
    });

    it('refreshes the token when the api answers with a 401', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'first_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::sequence()
                ->push([], 401)
                ->push(['idAplicativo' => 'app-1'], 200),
        ]);

        $response = app(WompiClientInterface::class)->getAplicativoData();

        expect($response->idAplicativo)->toBe('app-1');

        // The stale token was dropped and a new one was requested.
        expect(wompi_count_sent(fn ($request) => str_contains($request->url(), 'id.wompi.sv')))->toBe(2)
            ->and(wompi_count_sent(fn ($request) => str_contains($request->url(), '/Aplicativo')))->toBe(2);
    });

    it('gives up after a second 401', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'first_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::response([], 401),
        ]);

        try {
            app(WompiClientInterface::class)->getAplicativoData();

            $this->fail('The exception was not thrown.');
        } catch (WompiApiException $e) {
            expect($e->getStatusCode())->toBe(401);
        }
    });

    it('does not retry a post automatically', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::response([], 500),
        ]);

        try {
            app(WompiClientInterface::class)->createPaymentLink([
                'identificadorEnlaceComercio' => 'ORDER-1',
                'nombreProducto' => 'Test',
            ]);
        } catch (\Throwable) {
            // Expected.
        }

        expect(wompi_count_sent(fn ($request) => str_contains($request->url(), '/EnlacePago')))->toBe(1);
    });

    it('reports a failed handshake', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'error' => 'invalid_client',
            ], 401),
        ]);

        $this->expectException(WompiAuthenticationException::class);

        app(WompiClientInterface::class)->getAplicativoData();
    });
});
