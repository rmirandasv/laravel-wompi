<?php

use Rmirandasv\Wompi\DTOs\Requests\PaymentLinkRequestDTO;
use Rmirandasv\Wompi\DTOs\Requests\RecurringPaymentLinkRequestDTO;
use Rmirandasv\Wompi\DTOs\Requests\TokenizeCardRequestDTO;
use Rmirandasv\Wompi\DTOs\Requests\TokenizedTransactionRequestDTO;
use Rmirandasv\Wompi\DTOs\Requests\Transaction3DSRequestDTO;
use Rmirandasv\Wompi\Exceptions\WompiValidationException;

describe('Request DTO validation', function () {
    describe('PaymentLinkRequestDTO', function () {
        it('requires the merchant reference', function () {
            expect(fn () => new PaymentLinkRequestDTO(
                monto: 100.00,
                identificadorEnlaceComercio: '',
                nombreProducto: 'Test',
            ))->toThrow(WompiValidationException::class);
        });

        it('requires the product name', function () {
            expect(fn () => new PaymentLinkRequestDTO(
                monto: 100.00,
                identificadorEnlaceComercio: 'ORDER-1',
                nombreProducto: '',
            ))->toThrow(WompiValidationException::class);
        });

        it('rejects an amount below the minimum', function () {
            expect(fn () => new PaymentLinkRequestDTO(
                monto: 0.005,
                identificadorEnlaceComercio: 'ORDER-1',
                nombreProducto: 'Test',
            ))->toThrow(WompiValidationException::class);
        });

        it('rejects a reference longer than 500 characters', function () {
            expect(fn () => new PaymentLinkRequestDTO(
                monto: 100.00,
                identificadorEnlaceComercio: str_repeat('a', 501),
                nombreProducto: 'Test',
            ))->toThrow(WompiValidationException::class);
        });

        it('rejects an invalid number of installments', function () {
            expect(fn () => new PaymentLinkRequestDTO(
                monto: 100.00,
                identificadorEnlaceComercio: 'ORDER-1',
                nombreProducto: 'Test',
                cantidadMaximaCuotas: 13,
            ))->toThrow(WompiValidationException::class);
        });

        it('accepts the installments Wompi documents', function () {
            expect(fn () => new PaymentLinkRequestDTO(
                monto: 100.00,
                identificadorEnlaceComercio: 'ORDER-1',
                nombreProducto: 'Test',
                cantidadMaximaCuotas: 12,
            ))->not->toThrow(WompiValidationException::class);
        });

        it('rejects a retry duration outside of the allowed range', function () {
            expect(fn () => new PaymentLinkRequestDTO(
                monto: 100.00,
                identificadorEnlaceComercio: 'ORDER-1',
                nombreProducto: 'Test',
                configuracion: ['duracionInterfazIntentoMinutos' => 5],
            ))->toThrow(WompiValidationException::class);
        });

        it('builds the payload with the documented field names', function () {
            $dto = PaymentLinkRequestDTO::fromArray([
                'monto' => 100.00,
                'identificadorEnlaceComercio' => 'ORDER-1',
                'nombreProducto' => 'Test',
                'formaPago' => ['permitirTarjetaCreditoDebido' => true],
                'configuracion' => ['urlRedirect' => 'https://example.com/return'],
                'vigencia' => ['fechaInicio' => '2026-01-01T00:00:00'],
                'limitesDeUso' => ['cantidadMaximaPagosExitosos' => 1],
                'datosAdicionales' => ['orden' => 'ORDER-1'],
            ]);

            $payload = $dto->toArray();

            expect($payload)->toHaveKey('monto')
                ->and($payload)->toHaveKey('identificadorEnlaceComercio')
                ->and($payload)->toHaveKey('nombreProducto')
                ->and($payload)->toHaveKey('formaPago')
                ->and($payload)->toHaveKey('configuracion')
                ->and($payload)->toHaveKey('vigencia')
                ->and($payload)->toHaveKey('limitesDeUso')
                ->and($payload)->toHaveKey('datosAdicionales')
                ->and($payload['monto'])->toBe(100.00);
        });

        it('keeps unknown fields as extra data', function () {
            $dto = PaymentLinkRequestDTO::fromArray([
                'identificadorEnlaceComercio' => 'ORDER-1',
                'nombreProducto' => 'Test',
                'idGrupoTarjetas' => 'group-1',
            ]);

            expect($dto->toArray())->toHaveKey('idGrupoTarjetas');
        });
    });

    describe('TokenizeCardRequestDTO', function () {
        it('requires the expiry month and year', function () {
            expect(fn () => new TokenizeCardRequestDTO(
                numeroTarjeta: '4111111111111111',
                cvv: '123',
                mesVencimiento: 0,
                anioVencimiento: 0,
            ))->toThrow(WompiValidationException::class);
        });

        it('rejects a month outside of the range', function () {
            expect(fn () => new TokenizeCardRequestDTO(
                numeroTarjeta: '4111111111111111',
                cvv: '123',
                mesVencimiento: 13,
                anioVencimiento: 2030,
            ))->toThrow(WompiValidationException::class);
        });

        it('rejects a non numeric cvv', function () {
            expect(fn () => new TokenizeCardRequestDTO(
                numeroTarjeta: '4111111111111111',
                cvv: 'abc',
                mesVencimiento: 12,
                anioVencimiento: 2030,
            ))->toThrow(WompiValidationException::class);
        });

        it('uses the documented expiry field names', function () {
            $payload = TokenizeCardRequestDTO::fromArray([
                'numeroTarjeta' => '4111111111111111',
                'cvv' => '123',
                'mesVencimiento' => 12,
                'anioVencimiento' => 2030,
                'nombreEnTarjeta' => 'JUAN PEREZ',
            ])->toArray();

            expect($payload)->toHaveKey('mesVencimiento')
                ->and($payload)->toHaveKey('anioVencimiento')
                ->and($payload)->toHaveKey('nombreEnTarjeta')
                ->and($payload)->not->toHaveKey('mesExpiracion')
                ->and($payload)->not->toHaveKey('anioExpiracion')
                ->and($payload['mesVencimiento'])->toBe(12);
        });
    });

    describe('Transaction3DSRequestDTO', function () {
        it('requires the customer information', function () {
            expect(fn () => new Transaction3DSRequestDTO(
                monto: 100.00,
                tarjetaCreditoDebido: [
                    'numeroTarjeta' => '4111111111111111',
                    'cvv' => '123',
                    'mesVencimiento' => 12,
                    'anioVencimiento' => 2030,
                ],
                urlRedirect: 'https://example.com/return',
            ))->toThrow(WompiValidationException::class);
        });

        it('requires the card', function () {
            expect(fn () => new Transaction3DSRequestDTO(
                monto: 100.00,
                tarjetaCreditoDebido: [],
                urlRedirect: 'https://example.com/return',
                nombre: 'JUAN',
                apellido: 'PEREZ',
                email: 'juan@example.com',
                ciudad: 'SAN SALVADOR',
                direccion: 'Calle 1',
                idPais: 'SV',
                idRegion: 'SV-SS',
                codigoPostal: '1101',
                telefono: '70000000',
            ))->toThrow(WompiValidationException::class);
        });

        it('rejects an invalid email', function () {
            expect(fn () => new Transaction3DSRequestDTO(
                monto: 100.00,
                tarjetaCreditoDebido: [
                    'numeroTarjeta' => '4111111111111111',
                    'cvv' => '123',
                    'mesVencimiento' => 12,
                    'anioVencimiento' => 2030,
                ],
                urlRedirect: 'https://example.com/return',
                nombre: 'JUAN',
                apellido: 'PEREZ',
                email: 'not-an-email',
                ciudad: 'SAN SALVADOR',
                direccion: 'Calle 1',
                idPais: 'SV',
                idRegion: 'SV-SS',
                codigoPostal: '1101',
                telefono: '70000000',
            ))->toThrow(WompiValidationException::class);
        });

        it('rejects a phone number with letters', function () {
            expect(fn () => new Transaction3DSRequestDTO(
                monto: 100.00,
                tarjetaCreditoDebido: [
                    'numeroTarjeta' => '4111111111111111',
                    'cvv' => '123',
                    'mesVencimiento' => 12,
                    'anioVencimiento' => 2030,
                ],
                urlRedirect: 'https://example.com/return',
                nombre: 'JUAN',
                apellido: 'PEREZ',
                email: 'juan@example.com',
                ciudad: 'SAN SALVADOR',
                direccion: 'Calle 1',
                idPais: 'SV',
                idRegion: 'SV-SS',
                codigoPostal: '1101',
                telefono: '7000ABCD',
            ))->toThrow(WompiValidationException::class);
        });

        it('builds the nested card payload', function () {
            $payload = Transaction3DSRequestDTO::fromArray(wompi_3ds_payload())->toArray();

            expect($payload)->toHaveKey('tarjetaCreditoDebido')
                ->and($payload['tarjetaCreditoDebido'])->toHaveKey('numeroTarjeta')
                ->and($payload['tarjetaCreditoDebido'])->toHaveKey('cvv')
                ->and($payload['tarjetaCreditoDebido'])->toHaveKey('mesVencimiento')
                ->and($payload['tarjetaCreditoDebido'])->toHaveKey('anioVencimiento')
                ->and($payload)->toHaveKey('urlRedirect')
                ->and($payload)->toHaveKey('idPais')
                ->and($payload)->toHaveKey('idRegion')
                ->and($payload)->not->toHaveKey('mesExpiracion');
        });
    });

    describe('RecurringPaymentLinkRequestDTO', function () {
        it('requires the payment day inside of the valid range', function () {
            expect(fn () => new RecurringPaymentLinkRequestDTO(
                diaDePago: 32,
                nombre: 'Suscripcion',
                monto: 29.99,
            ))->toThrow(WompiValidationException::class);
        });

        it('requires the name and the amount', function () {
            expect(fn () => new RecurringPaymentLinkRequestDTO(
                diaDePago: 5,
                nombre: '',
                monto: 29.99,
            ))->toThrow(WompiValidationException::class);

            expect(fn () => new RecurringPaymentLinkRequestDTO(
                diaDePago: 5,
                nombre: 'Suscripcion',
                monto: 0,
            ))->toThrow(WompiValidationException::class);
        });

        it('builds the payload with the documented field names', function () {
            $payload = RecurringPaymentLinkRequestDTO::fromArray([
                'diaDePago' => 5,
                'nombre' => 'Suscripcion mensual',
                'monto' => 29.99,
                'idAplicativo' => 'app-1',
                'descripcionProducto' => 'Plan mensual',
            ])->toArray();

            expect($payload)->toBe([
                'diaDePago' => 5,
                'nombre' => 'Suscripcion mensual',
                'monto' => 29.99,
                'idAplicativo' => 'app-1',
                'descripcionProducto' => 'Plan mensual',
            ]);
        });
    });

    describe('TokenizedTransactionRequestDTO', function () {
        it('requires the token, the name and the email', function () {
            expect(fn () => new TokenizedTransactionRequestDTO(
                monto: 50.00,
                tokenTarjeta: '',
                emailCliente: 'juan@example.com',
                nombreCliente: 'JUAN',
            ))->toThrow(WompiValidationException::class);

            expect(fn () => new TokenizedTransactionRequestDTO(
                monto: 50.00,
                tokenTarjeta: 'tok_1',
                emailCliente: 'juan@example.com',
                nombreCliente: '',
            ))->toThrow(WompiValidationException::class);
        });

        it('builds the payload with the documented field names', function () {
            $payload = TokenizedTransactionRequestDTO::fromArray([
                'monto' => 50.00,
                'tokenTarjeta' => 'tok_abc123',
                'emailCliente' => 'juan@example.com',
                'nombreCliente' => 'JUAN PEREZ',
            ])->toArray();

            expect($payload)->toHaveKey('tokenTarjeta')
                ->and($payload)->toHaveKey('emailCliente')
                ->and($payload)->toHaveKey('nombreCliente')
                ->and($payload)->toHaveKey('monto');
        });
    });
});
