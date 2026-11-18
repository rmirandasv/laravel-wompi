<?php

use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\Contracts\WompiClientInterface;
use Rmirandasv\Wompi\DTOs\Responses\AplicativoResponseDTO;
use Rmirandasv\Wompi\DTOs\Responses\PaymentLinkResponseDTO;

describe('WompiClient API responses', function () {
    it('returns a typed payment link response', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/EnlacePago' => Http::response([
                'idEnlace' => 1384854,
                'urlEnlace' => 'https://wompi.sv/pay/1384854',
                'urlEnlaceLargo' => 'https://wompi.sv/l/1384854',
                'urlQrCodeEnlace' => 'https://wompi.sv/qr/1384854',
                'estaProductivo' => true,
            ], 200),
        ]);

        $response = app(WompiClientInterface::class)->createPaymentLink([
            'identificadorEnlaceComercio' => 'ORDER-1',
            'nombreProducto' => 'Test',
            'monto' => 100.00,
        ]);

        expect($response)->toBeInstanceOf(PaymentLinkResponseDTO::class)
            ->and($response->idEnlace)->toBe(1384854)
            ->and($response->urlEnlace)->toBe('https://wompi.sv/pay/1384854')
            ->and($response->urlQrCodeEnlace)->toBe('https://wompi.sv/qr/1384854')
            ->and($response->estaProductivo)->toBeTrue();
    });

    it('returns a paginated result for the tokenized cards', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/Tokenizacion*' => Http::response([
                'paginaActual' => 1,
                'cantidadPorPagina' => 50,
                'totalDeElementos' => 2,
                'totalPaginas' => 1,
                'resultado' => [
                    [
                        'tokenId' => 'tok_1',
                        'maskedCreditCardNumber' => '411111******1111',
                        'tokenProvider' => 'wompi',
                    ],
                    [
                        'tokenId' => 'tok_2',
                        'maskedCreditCardNumber' => '555555******4444',
                        'tokenProvider' => 'wompi',
                    ],
                ],
            ], 200),
        ]);

        $response = app(WompiClientInterface::class)->listTokenizedCards();

        expect($response->total())->toBe(2)
            ->and($response->totalPages())->toBe(1)
            ->and($response->currentPage())->toBe(1)
            ->and($response->items())->toHaveCount(2)
            ->and($response->items()[0]['tokenId'])->toBe('tok_1');
    });

    it('never exposes the client secret of the aplicativo', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/Aplicativo' => Http::response([
                'idAplicativo' => '467a4b3f-d880-4e28-ac56-0ab08f6b0ddb',
                'clientIdApi' => '467a4b3f-d880-4e28-ac56-0ab08f6b0ddb',
                'clientSecret' => 'super-secret-value',
                'numeroCuenta' => 'Retail5',
                'nombre' => 'Web Bitworks',
                'estaProductivo' => false,
                'aplicaPagoConPuntos' => true,
                'cuotasDisponibles' => [
                    ['cantidadCuotas' => 3, 'tasa' => 5],
                ],
            ], 200),
        ]);

        $response = app(WompiClientInterface::class)->getAplicativoData();

        expect($response)->toBeInstanceOf(AplicativoResponseDTO::class)
            ->and($response->idAplicativo)->toBe('467a4b3f-d880-4e28-ac56-0ab08f6b0ddb')
            ->and($response->cuotasDisponibles)->toHaveCount(1)
            ->and($response->toArray())->not->toHaveKey('clientSecret')
            ->and(json_encode($response))->not->toContain('super-secret-value')
            ->and($response->get('clientSecret'))->toBeNull();
    });

    it('serializes a response to json instead of an empty object', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/EnlacePago' => Http::response([
                'idEnlace' => 1,
                'urlEnlace' => 'https://wompi.sv/pay/1',
            ], 200),
        ]);

        $response = app(WompiClientInterface::class)->createPaymentLink([
            'identificadorEnlaceComercio' => 'ORDER-1',
            'nombreProducto' => 'Test',
        ]);

        expect(json_encode($response))->toContain('"urlEnlace":"https:\/\/wompi.sv\/pay\/1"')
            ->and(json_decode(json_encode($response), true))->toHaveKey('idEnlace');
    });

    it('sends the bearer token on every request', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'bearer_test',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::response([], 200),
        ]);

        app(WompiClientInterface::class)->getAplicativoData();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/Aplicativo')
                && $request->hasHeader('Authorization', 'Bearer bearer_test');
        });
    });

    it('returns the transaction detail of a purchase', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/TransaccionCompra/txn-1' => Http::response([
                'idTransaccion' => 'txn-1',
                'resultadoTransaccion' => 1,
                'esAprobada' => true,
                'esReal' => true,
                'monto' => 100.00,
                'montoOriginal' => 100.00,
                'codigoAutorizacion' => 'AUTH123',
                'mensaje' => 'APROBADA',
                'fechaTransaccion' => '2026-03-10T16:31:35.875Z',
                'idExterno' => 'ORDER-1',
                'datosAdicionales' => ['orden' => 'ORDER-1'],
            ], 200),
        ]);

        $response = app(WompiClientInterface::class)->getTransaction('txn-1');

        expect($response->idTransaccion)->toBe('txn-1')
            ->and($response->esAprobada)->toBeTrue()
            ->and($response->esReal)->toBeTrue()
            ->and($response->monto)->toEqual(100.00)
            ->and($response->resultEnum())->toBe(\Rmirandasv\Wompi\Enums\ResultadoTransaccion::APROBADA);
    });
});
