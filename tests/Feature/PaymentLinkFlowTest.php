<?php

use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\Facades\Wompi;

describe('Payment link flow', function () {
    it('creates a payment link and reads it back', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/EnlacePago' => Http::response([
                'idEnlace' => 1384854,
                'urlEnlace' => 'https://wompi.sv/pay/1384854',
                'urlQrCodeEnlace' => 'https://wompi.sv/qr/1384854',
                'estaProductivo' => true,
            ], 200),
            'https://api.wompi.sv/EnlacePago/1384854' => Http::response([
                'idEnlace' => 1384854,
                'nombreEnlace' => 'ORDER-1',
                'monto' => 100.00,
                'usable' => true,
                'cantidadPagosExitosos' => 1,
            ], 200),
        ]);

        $created = Wompi::createPaymentLink([
            'monto' => 100.00,
            'identificadorEnlaceComercio' => 'ORDER-1',
            'nombreProducto' => 'Camisa',
        ]);

        $fetched = Wompi::getPaymentLink($created->idEnlace);

        expect($created->urlEnlace)->toBe('https://wompi.sv/pay/1384854')
            ->and($fetched->usable)->toBeTrue()
            ->and($fetched->cantidadPagosExitosos)->toBe(1);
    });

    it('deactivates and reactivates a payment link', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/EnlacePago/1/desactivar' => Http::response([
                'idEnlace' => 1,
                'estaProductivo' => false,
            ], 200),
            'https://api.wompi.sv/EnlacePago/1/activar' => Http::response([
                'idEnlace' => 1,
                'estaProductivo' => true,
            ], 200),
        ]);

        $deactivated = Wompi::deactivatePaymentLink(1);
        $activated = Wompi::activatePaymentLink(1);

        expect($deactivated->estaProductivo)->toBeFalse()
            ->and($activated->estaProductivo)->toBeTrue();
    });

    it('builds a 3ds transaction that returns the url to complete the payment', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/TransaccionCompra/3Ds' => Http::response([
                'idTransaccion' => 'txn-3ds',
                'esReal' => true,
                'urlCompletarPago3Ds' => 'https://3ds.wompi.sv/authenticate',
                'monto' => 25.50,
            ], 200),
        ]);

        $transaction = Wompi::createTransaction3DS(wompi_3ds_payload());

        expect($transaction->idTransaccion)->toBe('txn-3ds')
            ->and($transaction->urlCompletarPago3Ds)->toBe('https://3ds.wompi.sv/authenticate')
            ->and($transaction->monto)->toEqual(25.50);
    });

    it('reads the regions needed to build a 3ds transaction', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/api/Regiones' => Http::response([
                ['idPais' => 'SV', 'nombrePais' => 'El Salvador'],
            ], 200),
        ]);

        $regions = Wompi::getRegions();

        expect($regions)->toHaveCount(1)
            ->and($regions[0]['idPais'])->toBe('SV');
    });
});
