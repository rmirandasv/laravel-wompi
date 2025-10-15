<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\WompiClient;

describe('Complete Payment Flow', function () {

    beforeEach(function () {
        Cache::flush();
    });

    it('can complete full payment link creation flow', function () {
        // Mock all HTTP requests for the complete flow
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'flow_token_123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://api.wompi.sv/v1/test/EnlacePago' => Http::response([
                'id' => 'payment_link_abc',
                'url' => 'https://wompi.sv/pay/payment_link_abc',
                'monto' => 150.00,
                'descripcion' => 'Test Product Purchase',
                'estado' => 'Activo',
            ], 201),
        ]);

        // Get the WompiClient instance from the container
        $wompiClient = app(WompiClient::class);

        // Create a payment link
        $paymentLink = $wompiClient->createPaymentLink([
            'monto' => 150.00,
            'descripcion' => 'Test Product Purchase',
            'urlRedirect' => 'https://mystore.com/payment/success',
            'urlWebhook' => 'https://mystore.com/webhooks/wompi',
        ]);

        // Assertions
        expect($paymentLink)
            ->toBeArray()
            ->toHaveKey('id')
            ->toHaveKey('url')
            ->and($paymentLink['monto'])->toEqual(150.00)
            ->and($paymentLink['estado'])->toBe('Activo');

        // Verify the correct HTTP requests were made
        Http::assertSent(function ($request) {
            return $request->url() === 'https://id.wompi.sv/test' &&
                   $request['grant_type'] === 'client_credentials';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/EnlacePago') &&
                   $request->hasHeader('Authorization', 'Bearer flow_token_123') &&
                   $request['monto'] === 150.00 &&
                   $request['descripcion'] === 'Test Product Purchase';
        });
    });

    it('can complete tokenization and recurring charge flow', function () {
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'token_flow_456',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/v1/test/Tokenizacion' => Http::response([
                'tokenId' => 'tok_subscription_123',
                'ultimos4Digitos' => '4242',
                'tipoTarjeta' => 'VISA',
                'mesExpiracion' => '12',
                'anioExpiracion' => '25',
            ], 201),
            'https://api.wompi.sv/v1/test/CargoRecurrente' => Http::response([
                'idTransaccion' => 'recurring_txn_789',
                'estado' => 'Aprobada',
                'monto' => 29.99,
                'codigoAutorizacion' => 'AUTH_REC_123',
            ], 201),
        ]);

        $wompiClient = app(WompiClient::class);

        // Step 1: Tokenize the card
        $tokenData = $wompiClient->tokenizeCard([
            'numeroTarjeta' => '4242424242424242',
            'cvv' => '123',
            'mesExpiracion' => '12',
            'anioExpiracion' => '25',
            'nombreTitular' => 'Juan Perez',
        ]);

        expect($tokenData)
            ->toHaveKey('tokenId')
            ->and($tokenData['tokenId'])->toBe('tok_subscription_123')
            ->and($tokenData['tipoTarjeta'])->toBe('VISA');

        // Step 2: Create a recurring charge with the token
        $chargeResult = $wompiClient->createRecurringCharge([
            'tokenId' => $tokenData['tokenId'],
            'monto' => 29.99,
            'descripcion' => 'Monthly Subscription - January',
        ]);

        expect($chargeResult)
            ->toHaveKey('idTransaccion')
            ->and($chargeResult['estado'])->toBe('Aprobada')
            ->and($chargeResult['monto'])->toBe(29.99);

        // Verify the flow - auth is cached, so we have: 1 auth + 1 tokenize + 1 charge
        Http::assertSentCount(3);
    });

    it('handles complete webhook processing flow', function () {
        $wompiClient = app(WompiClient::class);

        // Simulate webhook data from Wompi
        $webhookPayload = [
            'idTransaccion' => 'txn_webhook_123',
            'monto' => 250.00,
            'resultadoTransaccion' => 'ExitosaAprobada',
            'codigoAutorizacion' => 'WEBHOOK_AUTH_456',
            'formaPago' => 'VISA',
            'mensaje' => 'Transaccion exitosa',
        ];

        $webhookBody = json_encode($webhookPayload);
        $wompiHash = hash_hmac('sha256', $webhookBody, 'test_client_secret');

        // Create a simulated webhook request
        $request = \Illuminate\Http\Request::create(
            '/api/webhooks/wompi',
            'POST',
            [],
            [],
            [],
            ['HTTP_WOMPI_HASH' => $wompiHash],
            $webhookBody
        );

        // Validate and parse the webhook
        $parsedData = $wompiClient->validateWebhookRequest($request);

        // Check if it's a successful payment
        $isSuccessful = $wompiClient->isSuccessfulPayment($parsedData);

        expect($parsedData)
            ->toBeArray()
            ->toHaveKey('idTransaccion')
            ->and($parsedData['idTransaccion'])->toBe('txn_webhook_123')
            ->and($isSuccessful)->toBeTrue();
    });

    it('handles redirect URL validation flow', function () {
        $wompiClient = app(WompiClient::class);

        // Simulate redirect URL parameters from Wompi
        $redirectParams = [
            'idTransaccion' => 'txn_redirect_999',
            'monto' => '500.00',
            'esReal' => '1',
            'formaPago' => 'MASTERCARD',
            'esAprobada' => '1',
            'codigoAutorizacion' => 'REDIRECT_AUTH_789',
            'mensaje' => 'Pago exitoso',
        ];

        // Calculate the hash as Wompi would send it
        $concatenated = implode('', array_values($redirectParams));
        $wompiHash = hash_hmac('sha256', $concatenated, 'test_client_secret');

        // Validate the redirect parameters
        $isValid = $wompiClient->validateRedirectParams($redirectParams, $wompiHash);

        expect($isValid)->toBeTrue()
            ->and($redirectParams['esAprobada'])->toBe('1')
            ->and($redirectParams['idTransaccion'])->toBe('txn_redirect_999');
    });

    it('can retrieve and manage tokenized cards', function () {
        Http::fake([
            'https://id.wompi.sv/test' => Http::response([
                'access_token' => 'card_mgmt_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/v1/test/Tokenizacion' => Http::response([
                'tokenId' => 'tok_mgmt_123',
                'ultimos4Digitos' => '5555',
                'tipoTarjeta' => 'MASTERCARD',
            ], 201),
            'https://api.wompi.sv/v1/test/Tokenizacion/tok_mgmt_123' => Http::sequence()
                ->push(['tokenId' => 'tok_mgmt_123', 'ultimos4Digitos' => '5555'], 200)
                ->push(['success' => true, 'message' => 'Token eliminado'], 200),
        ]);

        $wompiClient = app(WompiClient::class);

        // Create token
        $token = $wompiClient->tokenizeCard([
            'numeroTarjeta' => '5555555555554444',
            'mesExpiracion' => '06',
            'anioExpiracion' => '26',
        ]);

        expect($token['tokenId'])->toBe('tok_mgmt_123');

        // Retrieve token
        $retrieved = $wompiClient->getTokenizedCard('tok_mgmt_123');
        expect($retrieved['tokenId'])->toBe('tok_mgmt_123');

        // Delete token
        $deleted = $wompiClient->deleteTokenizedCard('tok_mgmt_123');
        expect($deleted['success'])->toBeTrue();
    });
});

