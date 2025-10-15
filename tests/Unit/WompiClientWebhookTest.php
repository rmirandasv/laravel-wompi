<?php

use Illuminate\Http\Request;
use Rmirandasv\Wompi\Exceptions\ConfigurationException;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;
use Rmirandasv\Wompi\WompiClient;

describe('WompiClient Webhook Validation', function () {

    it('validates webhook signature correctly', function () {
        $client = app(WompiClient::class);
        
        $webhookBody = json_encode([
            'idTransaccion' => 'txn_123',
            'monto' => 100.00,
            'resultadoTransaccion' => 'ExitosaAprobada',
        ]);

        // Calculate expected hash using the test secret
        $expectedHash = hash_hmac('sha256', $webhookBody, 'test_client_secret');

        $isValid = $client->validateWebhookSignature($webhookBody, $expectedHash);

        expect($isValid)->toBeTrue();
    });

    it('rejects invalid webhook signature', function () {
        $client = app(WompiClient::class);
        
        $webhookBody = json_encode([
            'idTransaccion' => 'txn_123',
            'monto' => 100.00,
        ]);

        $invalidHash = 'invalid_hash_12345';

        $isValid = $client->validateWebhookSignature($webhookBody, $invalidHash);

        expect($isValid)->toBeFalse();
    });

    it('validates and parses complete webhook request', function () {
        $webhookData = [
            'idTransaccion' => 'txn_456',
            'monto' => 250.50,
            'resultadoTransaccion' => 'ExitosaAprobada',
            'codigoAutorizacion' => 'AUTH123',
        ];

        $webhookBody = json_encode($webhookData);
        $wompiHash = hash_hmac('sha256', $webhookBody, 'test_client_secret');

        // Create a mock request
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_WOMPI_HASH' => $wompiHash,
        ], $webhookBody);

        $client = app(WompiClient::class);
        $parsedData = $client->validateWebhookRequest($request);

        expect($parsedData)
            ->toBeArray()
            ->toHaveKey('idTransaccion')
            ->and($parsedData['idTransaccion'])->toBe('txn_456')
            ->and($parsedData['monto'])->toBe(250.50);
    });

    it('throws exception when webhook hash header is missing', function () {
        $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
            'idTransaccion' => 'txn_789',
        ]));

        $client = app(WompiClient::class);
        $client->validateWebhookRequest($request);
    })->throws(PaymentGatewayException::class, 'wompi_hash header is required');

    it('throws exception when webhook body is empty', function () {
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_WOMPI_HASH' => 'some_hash',
        ], '');

        $client = app(WompiClient::class);
        $client->validateWebhookRequest($request);
    })->throws(PaymentGatewayException::class, 'body is required');

    it('throws exception when webhook signature is invalid', function () {
        $webhookBody = json_encode(['idTransaccion' => 'txn_999']);
        $invalidHash = 'completely_wrong_hash';

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_WOMPI_HASH' => $invalidHash,
        ], $webhookBody);

        $client = app(WompiClient::class);
        $client->validateWebhookRequest($request);
    })->throws(PaymentGatewayException::class, 'signature is invalid');

    it('throws exception when webhook JSON is invalid', function () {
        $invalidJson = '{invalid json}';
        $hash = hash_hmac('sha256', $invalidJson, 'test_client_secret');

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_WOMPI_HASH' => $hash,
        ], $invalidJson);

        $client = app(WompiClient::class);
        $client->validateWebhookRequest($request);
    })->throws(PaymentGatewayException::class, 'json is invalid');

    it('identifies successful payment from webhook data', function () {
        $client = app(WompiClient::class);

        $successfulWebhook = [
            'idTransaccion' => 'txn_success',
            'resultadoTransaccion' => 'ExitosaAprobada',
        ];

        expect($client->isSuccessfulPayment($successfulWebhook))->toBeTrue();
    });

    it('identifies failed payment from webhook data', function () {
        $client = app(WompiClient::class);

        $failedWebhook = [
            'idTransaccion' => 'txn_failed',
            'resultadoTransaccion' => 'Rechazada',
        ];

        expect($client->isSuccessfulPayment($failedWebhook))->toBeFalse();
    });
});

