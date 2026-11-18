<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Rmirandasv\Wompi\Events\WompiPaymentProcessed;
use Rmirandasv\Wompi\Events\WompiWebhookReceived;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;
use Rmirandasv\Wompi\WompiClient;

describe('WompiClient webhook handling', function () {
    it('validates a webhook signature', function () {
        $client = app(WompiClient::class);

        $body = json_encode(wompi_webhook_payload());
        $hash = hash_hmac('sha256', $body, 'test_client_secret');

        expect($client->validateWebhookSignature($body, $hash))->toBeTrue()
            ->and($client->validateWebhookSignature($body, 'wrong_hash'))->toBeFalse();
    });

    it('rejects an empty signature without a deprecation', function () {
        $client = app(WompiClient::class);

        expect($client->validateWebhookSignature('{}', ''))->toBeFalse();
    });

    it('parses a webhook request', function () {
        $payload = wompi_webhook_payload();
        $body = json_encode($payload);
        $hash = hash_hmac('sha256', $body, 'test_client_secret');

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_WOMPI_HASH' => $hash,
        ], $body);

        $webhook = app(WompiClient::class)->handleWebhook($request);

        expect($webhook)->toBeInstanceOf(\Rmirandasv\Wompi\Webhooks\WompiWebhook::class)
            ->and($webhook->idTransaccion())->toBe('2bedafea-0924-49f0-927d-8c638e193990')
            ->and($webhook->reference())->toBe('OC1234')
            ->and($webhook->productName())->toBe('Camisa Azula')
            ->and($webhook->raw())->toBe($payload);
    });

    it('throws when the signature header is missing', function () {
        $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode(wompi_webhook_payload()));

        app(WompiClient::class)->handleWebhook($request);
    })->throws(PaymentGatewayException::class, 'wompi_hash header is required');

    it('throws when the body is empty', function () {
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_WOMPI_HASH' => 'some_hash',
        ], '');

        app(WompiClient::class)->handleWebhook($request);
    })->throws(PaymentGatewayException::class, 'body is required');

    it('throws when the signature is invalid', function () {
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_WOMPI_HASH' => 'completely_wrong_hash',
        ], json_encode(wompi_webhook_payload()));

        app(WompiClient::class)->handleWebhook($request);
    })->throws(PaymentGatewayException::class, 'signature is invalid');

    it('throws when the json is invalid', function () {
        $invalidJson = '{invalid json}';
        $hash = hash_hmac('sha256', $invalidJson, 'test_client_secret');

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_WOMPI_HASH' => $hash,
        ], $invalidJson);

        app(WompiClient::class)->handleWebhook($request);
    })->throws(PaymentGatewayException::class, 'json is invalid');

    describe('payment status detection', function () {
        it('detects a successful payment on the PascalCase payload Wompi sends', function () {
            $client = app(WompiClient::class);

            expect($client->isSuccessfulPayment(wompi_webhook_payload()))->toBeTrue();
        });

        it('detects a rejected payment', function () {
            $client = app(WompiClient::class);

            expect($client->isSuccessfulPayment(wompi_webhook_payload([
                'ResultadoTransaccion' => 'Rechazada',
            ])))->toBeFalse();
        });

        it('detects a successful payment on the camelCase payload of the API', function () {
            $client = app(WompiClient::class);

            expect($client->isSuccessfulPayment([
                'idTransaccion' => 'txn_123',
                'monto' => 100.00,
                'resultadoTransaccion' => 1,
                'esAprobada' => true,
                'esReal' => true,
            ]))->toBeTrue();
        });

        it('never marks a test transaction as successful', function () {
            $client = app(WompiClient::class);

            $payload = wompi_webhook_payload(['EsProductiva' => false]);

            expect($client->isSuccessfulPayment($payload))->toBeFalse();

            $webhook = \Rmirandasv\Wompi\Support\PayloadNormalizer::normalize($payload);

            expect($webhook->isApproved())->toBeTrue()
                ->and($webhook->isTestTransaction())->toBeTrue()
                ->and($webhook->isSuccessful())->toBeFalse();
        });
    });

    describe('event dispatching', function () {
        it('dispatches both events exactly once', function () {
            Event::fake();

            $payload = wompi_webhook_payload();
            $body = json_encode($payload);
            $hash = hash_hmac('sha256', $body, 'test_client_secret');

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WOMPI_HASH' => $hash,
            ], $body);

            app(WompiClient::class)->handleWebhook($request);

            Event::assertDispatchedTimes(WompiWebhookReceived::class, 1);
            Event::assertDispatchedTimes(WompiPaymentProcessed::class, 1);
        });

        it('does not dispatch the events twice for the same request', function () {
            Event::fake();

            $payload = wompi_webhook_payload();
            $body = json_encode($payload);
            $hash = hash_hmac('sha256', $body, 'test_client_secret');

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WOMPI_HASH' => $hash,
            ], $body);

            $client = app(WompiClient::class);

            $client->handleWebhook($request);
            $client->handleWebhook($request);

            Event::assertDispatchedTimes(WompiWebhookReceived::class, 1);
            Event::assertDispatchedTimes(WompiPaymentProcessed::class, 1);
        });

        it('reports a test transaction as unsuccessful', function () {
            Event::fake();

            $payload = wompi_webhook_payload(['EsProductiva' => false]);
            $body = json_encode($payload);
            $hash = hash_hmac('sha256', $body, 'test_client_secret');

            $request = Request::create('/webhook', 'POST', [], [], [], [
                'HTTP_WOMPI_HASH' => $hash,
            ], $body);

            app(WompiClient::class)->handleWebhook($request);

            Event::assertDispatched(WompiPaymentProcessed::class, function ($event) {
                return $event->isSuccessful === false
                    && $event->webhook->isTestTransaction() === true;
            });
        });
    });
});
