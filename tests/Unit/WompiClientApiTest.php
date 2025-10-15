<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;
use Rmirandasv\Wompi\WompiClient;

describe('WompiClient API Calls', function () {
    
    beforeEach(function () {
        Cache::flush();
        
        // Mock successful authentication by default
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
        ]);
    });

    it('can create a payment link', function () {
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/v1/test/EnlacePago' => Http::response([
                'id' => 'link_123',
                'url' => 'https://wompi.sv/pay/link_123',
                'amount' => 100.00,
            ], 201),
        ]);

        $client = app(WompiClient::class);
        $result = $client->createPaymentLink([
            'monto' => 100.00,
            'descripcion' => 'Test Payment',
            'urlRedirect' => 'https://mysite.com/success',
        ]);

        expect($result)
            ->toHaveKey('id')
            ->and($result['id'])->toBe('link_123')
            ->and($result['url'])->toBe('https://wompi.sv/pay/link_123');

        // Verify the correct endpoint was called with Bearer token
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/EnlacePago') &&
                   $request->hasHeader('Authorization', 'Bearer test_token') &&
                   $request['monto'] === 100.00;
        });
    });

    it('can create a 3DS transaction', function () {
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/v1/test/Transaccion' => Http::response([
                'idTransaccion' => 'txn_456',
                'estado' => 'Pendiente',
                'url3DS' => 'https://3ds.wompi.sv/authenticate',
            ], 201),
        ]);

        $client = app(WompiClient::class);
        $result = $client->createTransaction3DS([
            'monto' => 250.50,
            'numeroTarjeta' => '4111111111111111',
            'cvv' => '123',
            'mesExpiracion' => '12',
            'anioExpiracion' => '25',
        ]);

        expect($result)
            ->toHaveKey('idTransaccion')
            ->and($result['idTransaccion'])->toBe('txn_456')
            ->and($result['estado'])->toBe('Pendiente');
    });

    it('can tokenize a card', function () {
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/v1/test/Tokenizacion' => Http::response([
                'tokenId' => 'tok_abc123',
                'ultimos4Digitos' => '1111',
                'tipoTarjeta' => 'VISA',
            ], 201),
        ]);

        $client = app(WompiClient::class);
        $result = $client->tokenizeCard([
            'numeroTarjeta' => '4111111111111111',
            'mesExpiracion' => '12',
            'anioExpiracion' => '25',
        ]);

        expect($result)
            ->toHaveKey('tokenId')
            ->and($result['tokenId'])->toBe('tok_abc123')
            ->and($result['tipoTarjeta'])->toBe('VISA');
    });

    it('can get tokenized card', function () {
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/v1/test/Tokenizacion/tok_abc123' => Http::response([
                'tokenId' => 'tok_abc123',
                'ultimos4Digitos' => '1111',
                'tipoTarjeta' => 'VISA',
            ], 200),
        ]);

        $client = app(WompiClient::class);
        $result = $client->getTokenizedCard('tok_abc123');

        expect($result)
            ->toHaveKey('tokenId')
            ->and($result['tokenId'])->toBe('tok_abc123');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/Tokenizacion/tok_abc123');
        });
    });

    it('can delete tokenized card', function () {
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/v1/test/Tokenizacion/tok_abc123' => Http::response([
                'success' => true,
                'message' => 'Token deleted',
            ], 200),
        ]);

        $client = app(WompiClient::class);
        $result = $client->deleteTokenizedCard('tok_abc123');

        expect($result)
            ->toHaveKey('success')
            ->and($result['success'])->toBeTrue();
    });

    it('can create recurring charge', function () {
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/v1/test/CargoRecurrente' => Http::response([
                'idTransaccion' => 'rec_789',
                'estado' => 'Aprobada',
                'monto' => 50.00,
            ], 201),
        ]);

        $client = app(WompiClient::class);
        $result = $client->createRecurringCharge([
            'tokenId' => 'tok_abc123',
            'monto' => 50.00,
            'descripcion' => 'Monthly subscription',
        ]);

        expect($result)
            ->toHaveKey('idTransaccion')
            ->and($result['estado'])->toBe('Aprobada');
    });

    it('can get aplicativo data', function () {
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/v1/test/Aplicativo' => Http::response([
                'nombre' => 'Test App',
                'capacidades' => ['payment', 'tokenization'],
            ], 200),
        ]);

        $client = app(WompiClient::class);
        $result = $client->getAplicativoData();

        expect($result)
            ->toHaveKey('nombre')
            ->and($result['nombre'])->toBe('Test App');
    });

    it('throws PaymentGatewayException when API call fails', function () {
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/v1/test/EnlacePago' => Http::response([
                'error' => 'invalid_amount',
            ], 400),
        ]);

        $client = app(WompiClient::class);
        $client->createPaymentLink(['monto' => -100]);
    })->throws(PaymentGatewayException::class);
});

