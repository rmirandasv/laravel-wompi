<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\Exceptions\ConfigurationException;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;
use Rmirandasv\Wompi\WompiClient;

describe('WompiClient Authentication', function () {
    
    beforeEach(function () {
        Cache::flush();
    });

    it('throws exception when credentials are missing', function () {
        new WompiClient(null, 'https://api.test', 'client_id', 'secret');
    })->throws(ConfigurationException::class, 'Wompi credentials are not set');

    it('successfully obtains access token', function () {
        // Mock the HTTP request to the auth endpoint
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'test_access_token_123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            '*' => Http::response([], 200),
        ]);

        $client = app(WompiClient::class);
        $result = $client->getAplicativoData();

        // Verify the authentication request was made
        Http::assertSent(function ($request) {
            return $request->url() === 'https://id.wompi.sv/test' &&
                   $request['grant_type'] === 'client_credentials' &&
                   $request['client_id'] === 'test_client_id' &&
                   $request['client_secret'] === 'test_client_secret' &&
                   $request['audience'] === 'wompi_api';
        });
    });

    it('caches access token to avoid repeated authentication', function () {
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'cached_token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response(['data' => 'test'], 200),
        ]);

        $client = app(WompiClient::class);
        
        // First call - should authenticate
        $client->getAplicativoData();
        
        // Second call - should use cached token (no new auth request)
        $client->getAplicativoData();

        // Verify: 1 auth + 1 API call + 1 API call (using cached token)
        Http::assertSentCount(3);
        
        // Verify auth was only called once
        Http::assertSent(function ($request) {
            return $request->url() === 'https://id.wompi.sv/test';
        }, 1);
    });

    it('throws PaymentGatewayException when authentication fails', function () {
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'error' => 'invalid_client',
            ], 401),
        ]);

        $client = app(WompiClient::class);
        $client->getAplicativoData();
    })->throws(PaymentGatewayException::class, 'Failed to authenticate with Wompi');
});

