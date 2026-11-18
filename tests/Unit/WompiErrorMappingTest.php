<?php

use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\Contracts\WompiClientInterface;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;
use Rmirandasv\Wompi\Exceptions\WompiApiException;

describe('Wompi error mapping', function () {
    it('maps the ApiErrorResult shape onto the exception', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::response([
                'servicioError' => 'EnlacePago',
                'subTipoError' => 'DATOS_INVALIDOS',
                'mensajes' => [
                    'El monto debe ser mayor a cero',
                    'El nombre del producto es obligatorio',
                ],
            ], 400),
        ]);

        try {
            app(WompiClientInterface::class)->createPaymentLink([
                'identificadorEnlaceComercio' => 'ORDER-1',
                'nombreProducto' => 'Test',
            ]);

            $this->fail('The exception was not thrown.');
        } catch (WompiApiException $e) {
            expect($e->getStatusCode())->toBe(400)
                ->and($e->getServiceError())->toBe('EnlacePago')
                ->and($e->getErrorType())->toBe('DATOS_INVALIDOS')
                ->and($e->getMessages())->toBe([
                    'El monto debe ser mayor a cero',
                    'El nombre del producto es obligatorio',
                ])
                ->and($e->getMessage())->toContain('El monto debe ser mayor a cero')
                ->and($e->getMessage())->toContain('El nombre del producto es obligatorio')
                ->and($e->getResponseBody())->toHaveKey('servicioError');
        }
    });

    it('falls back to the singular mensaje field', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::response([
                'mensaje' => 'El monto debe ser mayor a cero',
            ], 400),
        ]);

        try {
            app(WompiClientInterface::class)->createPaymentLink([
                'identificadorEnlaceComercio' => 'ORDER-1',
                'nombreProducto' => 'Test',
            ]);

            $this->fail('The exception was not thrown.');
        } catch (WompiApiException $e) {
            expect($e->getMessages())->toBe(['El monto debe ser mayor a cero'])
                ->and($e->getMessage())->toContain('El monto debe ser mayor a cero');
        }
    });

    it('keeps the gateway exception contract', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::response([], 500),
        ]);

        $this->expectException(PaymentGatewayException::class);

        app(WompiClientInterface::class)->getAplicativoData();
    });

    it('reports a failed authentication with a dedicated exception', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'error' => 'invalid_client',
            ], 401),
        ]);

        $this->expectException(\Rmirandasv\Wompi\Exceptions\WompiAuthenticationException::class);

        app(WompiClientInterface::class)->getAplicativoData();
    });
});
