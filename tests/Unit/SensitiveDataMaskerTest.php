<?php

use Rmirandasv\Wompi\Support\SensitiveDataMasker;

describe('SensitiveDataMasker', function () {
    it('masks a card number at the root of the payload', function () {
        $masked = SensitiveDataMasker::mask([
            'numeroTarjeta' => '4111111111111111',
        ]);

        expect($masked['numeroTarjeta'])->toBe('4111********1111')
            ->and($masked['numeroTarjeta'])->not->toContain('4111111111111111');
    });

    it('masks a card number nested in the card object', function () {
        $masked = SensitiveDataMasker::mask([
            'tarjetaCreditoDebido' => [
                'numeroTarjeta' => '4111111111111111',
                'cvv' => '123',
                'mesVencimiento' => 12,
                'anioVencimiento' => 2030,
            ],
        ]);

        $card = $masked['tarjetaCreditoDebido'];

        expect($card['numeroTarjeta'])->toBe('4111********1111')
            ->and($card['cvv'])->toBe('***')
            ->and($card['mesVencimiento'])->toBe('***')
            ->and($card['anioVencimiento'])->toBe('***');
    });

    it('masks a card number nested in a tokenized card object', function () {
        $masked = SensitiveDataMasker::mask([
            'datosTarjetaTokenizada' => [
                'numeroTarjeta' => '5555555555554444',
                'cvv' => '987',
            ],
        ]);

        expect($masked['datosTarjetaTokenizada']['numeroTarjeta'])->toBe('5555********4444')
            ->and($masked['datosTarjetaTokenizada']['cvv'])->toBe('***');
    });

    it('masks any value that looks like a card number even under an unknown key', function () {
        $masked = SensitiveDataMasker::mask([
            'campoFuturo' => '4111 1111 1111 1111',
        ]);

        expect($masked['campoFuturo'])->not->toContain('4111 1111 1111 1111')
            ->and($masked['campoFuturo'])->toStartWith('4111');
    });

    it('masks a cvv like value found inside a card object', function () {
        $masked = SensitiveDataMasker::mask([
            'tarjeta' => [
                'campoDesconocido' => '123',
            ],
        ]);

        expect($masked['tarjeta']['campoDesconocido'])->toBe('***');
    });

    it('leaves harmless payloads untouched', function () {
        $payload = [
            'monto' => 100.00,
            'identificadorEnlaceComercio' => 'ORDER-123',
            'idTransaccion' => '2bedafea-0924-49f0-927d-8c638e193990',
            'codigoAutorizacion' => 'AUTH123',
            'cantidad' => 1,
            'esProductiva' => true,
        ];

        expect(SensitiveDataMasker::mask($payload))->toBe($payload);
    });

    it('does not mask an authorization code that only looks numeric', function () {
        $masked = SensitiveDataMasker::mask([
            'codigoAutorizacion' => '270015',
        ]);

        expect($masked['codigoAutorizacion'])->toBe('270015');
    });
});
