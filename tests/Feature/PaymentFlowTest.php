<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\Facades\Wompi;

describe('Complete payment flow', function () {
    beforeEach(function () {
        Cache::flush();
    });

    it('creates a payment link through the facade', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'flow_token_123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://api.wompi.sv/EnlacePago' => Http::response([
                'idEnlace' => 1384854,
                'urlEnlace' => 'https://wompi.sv/pay/1384854',
                'urlEnlaceLargo' => 'https://wompi.sv/l/1384854',
                'urlQrCodeEnlace' => 'https://wompi.sv/qr/1384854',
                'estaProductivo' => true,
            ], 200),
        ]);

        $paymentLink = Wompi::createPaymentLink([
            'monto' => 150.00,
            'identificadorEnlaceComercio' => 'ORDER-150',
            'nombreProducto' => 'Test Product Purchase',
            'configuracion' => [
                'urlRedirect' => 'https://mystore.com/payment/success',
                'urlWebhook' => 'https://mystore.com/webhooks/wompi',
            ],
        ]);

        expect($paymentLink->idEnlace)->toBe(1384854)
            ->and($paymentLink->urlEnlace)->toBe('https://wompi.sv/pay/1384854')
            ->and($paymentLink->estaProductivo)->toBeTrue();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://id.wompi.sv/connect/token'
                && $request['grant_type'] === 'client_credentials';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/EnlacePago')
                && $request->hasHeader('Authorization', 'Bearer flow_token_123')
                && $request['monto'] === 150.00
                && $request['identificadorEnlaceComercio'] === 'ORDER-150'
                && $request['nombreProducto'] === 'Test Product Purchase';
        });
    });

    it('tokenizes a card and charges it without 3ds', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'token_flow_456',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/Tokenizacion' => Http::response([
                'token' => 'tok_subscription_123',
                'tarjetaEnmascarada' => '424242******4242',
            ], 200),
            'https://api.wompi.sv/TransaccionCompra/TokenizadaSin3Ds' => Http::response([
                'idTransaccion' => 'recurring_txn_789',
                'esAprobada' => true,
                'esReal' => true,
                'monto' => 29.99,
                'codigoAutorizacion' => 'AUTH_REC_123',
            ], 200),
        ]);

        $token = Wompi::tokenizeCard([
            'numeroTarjeta' => '4242424242424242',
            'cvv' => '123',
            'mesVencimiento' => 12,
            'anioVencimiento' => 2030,
            'nombreEnTarjeta' => 'Juan Perez',
        ]);

        expect($token->token)->toBe('tok_subscription_123')
            ->and($token->tarjetaEnmascarada)->toBe('424242******4242');

        $charge = Wompi::createTokenizedTransaction([
            'tokenTarjeta' => $token->token,
            'monto' => 29.99,
            'nombreCliente' => 'Juan Perez',
            'emailCliente' => 'juan@example.com',
        ]);

        expect($charge->idTransaccion)->toBe('recurring_txn_789')
            ->and($charge->esAprobada)->toBeTrue()
            ->and($charge->monto)->toEqual(29.99);

        // The token is cached, so there is one auth request plus two calls.
        Http::assertSentCount(3);
    });

    it('handles a complete webhook processing flow', function () {
        $payload = wompi_webhook_payload();
        $body = json_encode($payload);
        $hash = hash_hmac('sha256', $body, 'test_client_secret');

        $request = \Illuminate\Http\Request::create(
            '/api/webhooks/wompi',
            'POST',
            [],
            [],
            [],
            ['HTTP_WOMPI_HASH' => $hash],
            $body
        );

        $webhook = Wompi::handleWebhook($request);

        expect($webhook->idTransaccion())->toBe('2bedafea-0924-49f0-927d-8c638e193990')
            ->and($webhook->reference())->toBe('OC1234')
            ->and(Wompi::isSuccessfulPayment($payload))->toBeTrue();
    });

    it('handles a redirect url validation flow', function () {
        $redirectParams = [
            'idTransaccion' => 'txn_redirect_999',
            'monto' => '500.00',
            'esReal' => 'True',
            'formaPago' => 'MASTERCARD',
            'esAprobada' => 'True',
            'codigoAutorizacion' => 'REDIRECT_AUTH_789',
            'mensaje' => 'Pago exitoso',
        ];

        $concatenated = implode('', array_values($redirectParams));
        $hash = hash_hmac('sha256', $concatenated, 'test_client_secret');

        expect(Wompi::validateRedirectParams($redirectParams, $hash))->toBeTrue()
            ->and(Wompi::isRedirectApproved($redirectParams))->toBeTrue();
    });

    it('lists and deletes tokenized cards', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'card_mgmt_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/Tokenizacion*' => Http::response([
                'paginaActual' => 1,
                'cantidadPorPagina' => 50,
                'totalDeElementos' => 1,
                'totalPaginas' => 1,
                'resultado' => [
                    [
                        'tokenId' => 'tok_mgmt_123',
                        'maskedCreditCardNumber' => '555555******4444',
                    ],
                ],
            ], 200),
            'https://api.wompi.sv/Tokenizacion/tok_mgmt_123' => Http::response([], 204),
        ]);

        $cards = Wompi::listTokenizedCards();

        expect($cards->total())->toBe(1)
            ->and($cards->items()[0]['tokenId'])->toBe('tok_mgmt_123');

        Wompi::deleteTokenizedCard('tok_mgmt_123');

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/Tokenizacion/tok_mgmt_123');
        });
    });
});
