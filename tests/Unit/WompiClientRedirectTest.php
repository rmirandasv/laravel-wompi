<?php

use Rmirandasv\Wompi\WompiClient;

describe('WompiClient Redirect URL Validation', function () {

    it('validates redirect URL parameters correctly', function () {
        $client = app(WompiClient::class);

        $params = [
            'idTransaccion' => 'txn_123',
            'monto' => '100.00',
            'esReal' => '1',
            'formaPago' => 'VISA',
            'esAprobada' => '1',
            'codigoAutorizacion' => 'AUTH123',
            'mensaje' => 'Transaccion Aprobada',
        ];

        // Calculate expected hash
        $concatenated = $params['idTransaccion'] . 
                       $params['monto'] . 
                       $params['esReal'] . 
                       $params['formaPago'] . 
                       $params['esAprobada'] . 
                       $params['codigoAutorizacion'] . 
                       $params['mensaje'];
        
        $expectedHash = hash_hmac('sha256', $concatenated, 'test_client_secret');

        $isValid = $client->validateRedirectParams($params, $expectedHash);

        expect($isValid)->toBeTrue();
    });

    it('rejects invalid redirect URL hash', function () {
        $client = app(WompiClient::class);

        $params = [
            'idTransaccion' => 'txn_456',
            'monto' => '250.00',
            'esReal' => '1',
            'formaPago' => 'MASTERCARD',
            'esAprobada' => '0',
            'codigoAutorizacion' => '',
            'mensaje' => 'Tarjeta invalida',
        ];

        $invalidHash = 'invalid_hash_value';

        $isValid = $client->validateRedirectParams($params, $invalidHash);

        expect($isValid)->toBeFalse();
    });

    it('handles missing parameters gracefully', function () {
        $client = app(WompiClient::class);

        $params = [
            'idTransaccion' => 'txn_789',
            'monto' => '75.00',
            // Missing other fields
        ];

        // Calculate hash with missing fields (empty strings)
        $concatenated = $params['idTransaccion'] . $params['monto'];
        $expectedHash = hash_hmac('sha256', $concatenated, 'test_client_secret');

        $isValid = $client->validateRedirectParams($params, $expectedHash);

        expect($isValid)->toBeTrue();
    });

    it('validates approved transaction parameters', function () {
        $client = app(WompiClient::class);

        $params = [
            'idTransaccion' => 'txn_approved',
            'monto' => '500.00',
            'esReal' => '1',
            'formaPago' => 'VISA',
            'esAprobada' => '1',
            'codigoAutorizacion' => 'AUTH999',
            'mensaje' => 'Aprobada',
        ];

        $concatenated = implode('', array_values($params));
        $hash = hash_hmac('sha256', $concatenated, 'test_client_secret');

        $isValid = $client->validateRedirectParams($params, $hash);

        expect($isValid)->toBeTrue()
            ->and($params['esAprobada'])->toBe('1');
    });

    it('validates rejected transaction parameters', function () {
        $client = app(WompiClient::class);

        $params = [
            'idTransaccion' => 'txn_rejected',
            'monto' => '150.00',
            'esReal' => '1',
            'formaPago' => 'VISA',
            'esAprobada' => '0',
            'codigoAutorizacion' => '',
            'mensaje' => 'Fondos insuficientes',
        ];

        $concatenated = implode('', array_values($params));
        $hash = hash_hmac('sha256', $concatenated, 'test_client_secret');

        $isValid = $client->validateRedirectParams($params, $hash);

        expect($isValid)->toBeTrue()
            ->and($params['esAprobada'])->toBe('0');
    });
});

