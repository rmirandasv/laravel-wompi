<?php

use Rmirandasv\Wompi\WompiClient;

describe('WompiClient redirect URL validation', function () {
    it('validates the parameters of a payment link redirect', function () {
        $params = [
            'identificadorEnlaceComercio' => 'test-link',
            'idTransaccion' => 'd0efabed-12e0-4915-abc1-0d3689906700',
            'idEnlace' => '1384854',
            'monto' => '10',
            'hash' => 'ignored',
        ];

        $concatenated = $params['identificadorEnlaceComercio']
            .$params['idTransaccion']
            .$params['idEnlace']
            .$params['monto'];

        $hash = hash_hmac('sha256', $concatenated, 'test_client_secret');

        expect(app(WompiClient::class)->validateRedirectParams($params, $hash))->toBeTrue();
    });

    it('validates the parameters of an API redirect', function () {
        $params = [
            'idTransaccion' => '0abac760-e642-46e0-b658-0f526957b88a',
            'monto' => '10',
            'esReal' => 'True',
            'formaPago' => 'PagoNormal',
            'esAprobada' => 'True',
            'codigoAutorizacion' => '270015',
            'mensaje' => 'AUTORIZADO',
            'hash' => 'ignored',
        ];

        $concatenated = $params['idTransaccion']
            .$params['monto']
            .$params['esReal']
            .$params['formaPago']
            .$params['esAprobada']
            .$params['codigoAutorizacion']
            .$params['mensaje'];

        $hash = hash_hmac('sha256', $concatenated, 'test_client_secret');

        expect(app(WompiClient::class)->validateRedirectParams($params, $hash))->toBeTrue();
    });

    it('rejects an invalid hash', function () {
        $params = [
            'idTransaccion' => 'txn_456',
            'monto' => '250.00',
            'esReal' => 'true',
            'formaPago' => 'MASTERCARD',
            'esAprobada' => 'false',
            'codigoAutorizacion' => '',
            'mensaje' => 'Tarjeta invalida',
        ];

        expect(app(WompiClient::class)->validateRedirectParams($params, 'invalid_hash_value'))->toBeFalse();
    });

    it('rejects a redirect that is missing a parameter', function () {
        $params = [
            'idTransaccion' => 'txn_789',
            'monto' => '75.00',
        ];

        // A hash computed over the truncated string must not be accepted.
        $hash = hash_hmac('sha256', $params['idTransaccion'].$params['monto'], 'test_client_secret');

        expect(app(WompiClient::class)->validateRedirectParams($params, $hash))->toBeFalse();
    });

    it('rejects an empty hash', function () {
        expect(app(WompiClient::class)->validateRedirectParams([
            'idTransaccion' => 'txn_789',
            'monto' => '75.00',
        ], ''))->toBeFalse();
    });

    it('accepts an empty authorization code on a rejected transaction', function () {
        $params = [
            'idTransaccion' => 'txn_rejected',
            'monto' => '150.00',
            'esReal' => 'true',
            'formaPago' => 'VISA',
            'esAprobada' => 'false',
            'codigoAutorizacion' => '',
            'mensaje' => 'Fondos insuficientes',
        ];

        $concatenated = $params['idTransaccion']
            .$params['monto']
            .$params['esReal']
            .$params['formaPago']
            .$params['esAprobada']
            .$params['codigoAutorizacion']
            .$params['mensaje'];

        $hash = hash_hmac('sha256', $concatenated, 'test_client_secret');

        expect(app(WompiClient::class)->validateRedirectParams($params, $hash))->toBeTrue();
    });

    describe('approval detection', function () {
        it('detects an approved transaction whatever the casing is', function () {
            $client = app(WompiClient::class);

            expect($client->isRedirectApproved(['esAprobada' => 'true']))->toBeTrue()
                ->and($client->isRedirectApproved(['esAprobada' => 'True']))->toBeTrue()
                ->and($client->isRedirectApproved(['esAprobada' => 'TRUE']))->toBeTrue()
                ->and($client->isRedirectApproved(['esAprobada' => '1']))->toBeTrue()
                ->and($client->isRedirectApproved(['esAprobada' => true]))->toBeTrue();
        });

        it('detects a rejected transaction', function () {
            $client = app(WompiClient::class);

            expect($client->isRedirectApproved(['esAprobada' => 'false']))->toBeFalse()
                ->and($client->isRedirectApproved(['esAprobada' => 'False']))->toBeFalse()
                ->and($client->isRedirectApproved(['esAprobada' => '0']))->toBeFalse()
                ->and($client->isRedirectApproved([]))->toBeFalse();
        });
    });
});
