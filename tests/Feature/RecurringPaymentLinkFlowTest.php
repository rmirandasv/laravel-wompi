<?php

use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\Facades\Wompi;

/**
 * A subscription in Wompi is a recurring payment link the customer affiliates
 * themselves to. Charging an already tokenized card is a different operation
 * and is covered by the tokenized transaction endpoint.
 */
describe('Recurring payment link flow', function () {
    it('creates a recurring payment link the customer can subscribe to', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/EnlacePagoRecurrente' => Http::response([
                'idEnlace' => 'rec-1',
                'urlEnlace' => 'https://wompi.sv/s/rec-1',
                'urlEnlaceLargo' => 'https://wompi.sv/l/rec-1',
                'urlQrCodeEnlace' => 'https://wompi.sv/qr/rec-1',
                'estaProductivo' => true,
            ], 200),
        ]);

        $recurring = Wompi::createRecurringPaymentLink([
            'diaDePago' => 5,
            'nombre' => 'Suscripcion mensual',
            'monto' => 29.99,
            'descripcionProducto' => 'Plan mensual de prueba',
        ]);

        expect($recurring->idEnlace)->toBe('rec-1')
            ->and($recurring->urlEnlace)->toBe('https://wompi.sv/s/rec-1')
            ->and($recurring->urlQrCodeEnlace)->toBe('https://wompi.sv/qr/rec-1');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/EnlacePagoRecurrente')
                && $request['diaDePago'] === 5
                && $request['nombre'] === 'Suscripcion mensual'
                && $request['monto'] === 29.99
                && $request['descripcionProducto'] === 'Plan mensual de prueba';
        });
    });

    it('lists the subscribers of a recurring payment link', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/EnlacePagoRecurrente/rec-1/suscripciones' => Http::response([
                'paginaActual' => 1,
                'cantidadPorPagina' => 10,
                'totalDeElementos' => 1,
                'totalPaginas' => 1,
                'resultado' => [
                    [
                        'id' => 'sub-1',
                        'idSuscriptor' => 'CUSTOMER-1',
                        'nombreSuscriptor' => 'JUAN PEREZ',
                        'monto' => 29.99,
                        'pagosRealizados' => 2,
                        'estado' => 2,
                        'fechaInicio' => '2026-01-05T00:00:00',
                        'diaPago' => 5,
                    ],
                ],
            ], 200),
        ]);

        $subscriptions = Wompi::listRecurringSubscriptions('rec-1');

        expect($subscriptions->total())->toBe(1)
            ->and($subscriptions->items()[0]['idSuscriptor'])->toBe('CUSTOMER-1')
            ->and($subscriptions->items()[0]['pagosRealizados'])->toBe(2);
    });

    it('lists, reads, updates and deactivates the recurring payment links', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/EnlacePagoRecurrente' => Http::response([
                'paginaActual' => 1,
                'cantidadPorPagina' => 10,
                'totalDeElementos' => 1,
                'totalPaginas' => 1,
                'resultado' => [
                    [
                        'id' => 'rec-1',
                        'nombre' => 'Suscripcion mensual',
                        'monto' => 29.99,
                        'diaDePago' => 5,
                        'estaActivo' => true,
                    ],
                ],
            ], 200),
            'https://api.wompi.sv/EnlacePagoRecurrente/rec-1' => Http::response([
                'id' => 'rec-1',
                'nombre' => 'Suscripcion mensual',
                'monto' => 39.99,
                'diaDePago' => 5,
                'estaActivo' => true,
            ], 200),
        ]);

        $list = Wompi::listRecurringPaymentLinks();

        expect($list->total())->toBe(1)
            ->and($list->items()[0]['nombre'])->toBe('Suscripcion mensual');

        $updated = Wompi::updateRecurringPaymentLink('rec-1', [
            'nombre' => 'Suscripcion mensual',
            'monto' => 39.99,
        ]);

        expect($updated->monto)->toEqual(39.99);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/EnlacePagoRecurrente/rec-1');
        });
    });
});
