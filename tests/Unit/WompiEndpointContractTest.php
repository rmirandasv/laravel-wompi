<?php

use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\Contracts\WompiClientInterface;

/**
 * The contract between the client and the Wompi API.
 *
 * Every scenario below declares the verb and the path template the client is
 * expected to call. The test then asserts two things:
 *
 *   1. The client really called that verb and path.
 *   2. Wompi documents that verb and path in the OpenAPI specification.
 *
 * The second assertion is what protects the package from the class of bug
 * that broke v1: an endpoint that no longer exists (or never existed) is
 * caught here instead of returning a 404 in production.
 *
 * The specification fixture is a trimmed copy of
 * https://api.wompi.sv/swagger/v1/swagger.json.
 */
describe('Wompi endpoint contract', function () {
    it('creates a payment link on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->createPaymentLink([
            'identificadorEnlaceComercio' => 'ORDER-1',
            'nombreProducto' => 'Test',
            'monto' => 100.00,
        ]));

        expect($request['verb'])->toBe('POST')
            ->and($request['path'])->toBe('/EnlacePago')
            ->and(wompi_spec_has('POST', '/EnlacePago'))->toBeTrue();
    });

    it('lists payment links on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->listPaymentLinks());

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/EnlacePago')
            ->and(wompi_spec_has('GET', '/EnlacePago'))->toBeTrue();
    });

    it('gets a payment link on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->getPaymentLink(123));

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/EnlacePago/123')
            ->and(wompi_path_matches('/EnlacePago/{id}', $request['path']))->toBeTrue()
            ->and(wompi_spec_has('GET', '/EnlacePago/{id}'))->toBeTrue();
    });

    it('updates a payment link on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->updatePaymentLink(123, [
            'identificadorEnlaceComercio' => 'ORDER-1',
            'nombreProducto' => 'Test',
        ]));

        expect($request['verb'])->toBe('PUT')
            ->and($request['path'])->toBe('/EnlacePago/123')
            ->and(wompi_spec_has('PUT', '/EnlacePago/{id}'))->toBeTrue();
    });

    it('activates and deactivates a payment link on the documented endpoints', function () {
        $activate = wompi_record(fn ($client) => $client->activatePaymentLink(123));
        $deactivate = wompi_record(fn ($client) => $client->deactivatePaymentLink(123));

        expect($activate['verb'])->toBe('PUT')
            ->and($activate['path'])->toBe('/EnlacePago/123/activar')
            ->and(wompi_spec_has('PUT', '/EnlacePago/{id}/activar'))->toBeTrue()
            ->and($deactivate['verb'])->toBe('PUT')
            ->and($deactivate['path'])->toBe('/EnlacePago/123/desactivar')
            ->and(wompi_spec_has('PUT', '/EnlacePago/{id}/desactivar'))->toBeTrue();
    });

    it('creates a 3DS transaction on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->createTransaction3DS(wompi_3ds_payload()));

        expect($request['verb'])->toBe('POST')
            ->and($request['path'])->toBe('/TransaccionCompra/3Ds')
            ->and(wompi_spec_has('POST', '/TransaccionCompra/3Ds'))->toBeTrue();
    });

    it('nests the card inside tarjetaCreditoDebido', function () {
        $request = wompi_record(fn ($client) => $client->createTransaction3DS(wompi_3ds_payload()));

        expect($request['body'])->toHaveKey('tarjetaCreditoDebido')
            ->and($request['body']['tarjetaCreditoDebido'])->toHaveKey('numeroTarjeta')
            ->and($request['body']['tarjetaCreditoDebido'])->toHaveKey('cvv')
            ->and($request['body']['tarjetaCreditoDebido'])->toHaveKey('mesVencimiento')
            ->and($request['body']['tarjetaCreditoDebido'])->toHaveKey('anioVencimiento');
    });

    it('creates a tokenized transaction on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->createTokenizedTransaction([
            'monto' => 50.00,
            'tokenTarjeta' => 'tok_abc123',
            'nombreCliente' => 'JUAN PEREZ',
            'emailCliente' => 'juan@example.com',
        ]));

        expect($request['verb'])->toBe('POST')
            ->and($request['path'])->toBe('/TransaccionCompra/TokenizadaSin3Ds')
            ->and(wompi_spec_has('POST', '/TransaccionCompra/TokenizadaSin3Ds'))->toBeTrue();
    });

    it('gets a transaction on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->getTransaction('txn-123'));

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/TransaccionCompra/txn-123')
            ->and(wompi_path_matches('/TransaccionCompra/{id}', $request['path']))->toBeTrue()
            ->and(wompi_spec_has('GET', '/TransaccionCompra/{id}'))->toBeTrue();
    });

    it('lists transactions on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->listTransactions());

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/TransaccionCompra')
            ->and(wompi_spec_has('GET', '/TransaccionCompra'))->toBeTrue();
    });

    it('gets the receipt on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->getTransactionReceipt('txn-123'));

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/TransaccionCompra/txn-123/comprobante')
            ->and(wompi_spec_has('GET', '/TransaccionCompra/{idTransaccion}/comprobante'))->toBeTrue();
    });

    it('gets the daily statistics on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->getDailyTransactionStats());

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/TransaccionCompra/estadistica/diaria')
            ->and(wompi_spec_has('GET', '/TransaccionCompra/estadistica/diaria'))->toBeTrue();
    });

    it('tokenizes a card on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->tokenizeCard([
            'numeroTarjeta' => '4111111111111111',
            'cvv' => '123',
            'mesVencimiento' => 12,
            'anioVencimiento' => 2030,
        ]));

        expect($request['verb'])->toBe('POST')
            ->and($request['path'])->toBe('/Tokenizacion')
            ->and(wompi_spec_has('POST', '/Tokenizacion'))->toBeTrue();
    });

    it('lists tokenized cards on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->listTokenizedCards());

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/Tokenizacion')
            ->and(wompi_spec_has('GET', '/Tokenizacion'))->toBeTrue()
            ->and($request['query'])->toContain('Pagina=1')
            ->and($request['query'])->toContain('CantidadPagina=50');
    });

    it('deletes a tokenized card on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->deleteTokenizedCard('tok_abc123'));

        expect($request['verb'])->toBe('DELETE')
            ->and($request['path'])->toBe('/Tokenizacion/tok_abc123')
            ->and(wompi_spec_has('DELETE', '/Tokenizacion/{tokenId}'))->toBeTrue();
    });

    it('creates a recurring payment link on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->createRecurringPaymentLink([
            'diaDePago' => 5,
            'nombre' => 'Suscripcion mensual',
            'monto' => 29.99,
            'descripcionProducto' => 'Plan mensual',
        ]));

        expect($request['verb'])->toBe('POST')
            ->and($request['path'])->toBe('/EnlacePagoRecurrente')
            ->and(wompi_spec_has('POST', '/EnlacePagoRecurrente'))->toBeTrue();
    });

    it('lists recurring payment links on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->listRecurringPaymentLinks());

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/EnlacePagoRecurrente')
            ->and(wompi_spec_has('GET', '/EnlacePagoRecurrente'))->toBeTrue();
    });

    it('gets a recurring payment link on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->getRecurringPaymentLink('rec-1'));

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/EnlacePagoRecurrente/rec-1')
            ->and(wompi_path_matches('/EnlacePagoRecurrente/{id}', $request['path']))->toBeTrue()
            ->and(wompi_spec_has('GET', '/EnlacePagoRecurrente/{id}'))->toBeTrue();
    });

    it('updates a recurring payment link on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->updateRecurringPaymentLink('rec-1', [
            'nombre' => 'Nuevo nombre',
            'monto' => 39.99,
        ]));

        expect($request['verb'])->toBe('PUT')
            ->and($request['path'])->toBe('/EnlacePagoRecurrente/rec-1')
            ->and(wompi_spec_has('PUT', '/EnlacePagoRecurrente/{id}'))->toBeTrue();
    });

    it('deactivates a recurring payment link on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->deactivateRecurringPaymentLink('rec-1'));

        expect($request['verb'])->toBe('POST')
            ->and($request['path'])->toBe('/EnlacePagoRecurrente/rec-1')
            ->and(wompi_spec_has('POST', '/EnlacePagoRecurrente/{id}'))->toBeTrue();
    });

    it('lists the subscriptions of a recurring payment link on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->listRecurringSubscriptions('rec-1'));

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/EnlacePagoRecurrente/rec-1/suscripciones')
            ->and(wompi_spec_has('GET', '/EnlacePagoRecurrente/{id}/suscripciones'))->toBeTrue();
    });

    it('gets the aplicativo data on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->getAplicativoData());

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/Aplicativo')
            ->and(wompi_spec_has('GET', '/Aplicativo'))->toBeTrue();
    });

    it('gets an aplicativo by id on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->getAplicativoById('app-1'));

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/Aplicativo/app-1')
            ->and(wompi_path_matches('/Aplicativo/{idAplicativo}', $request['path']))->toBeTrue()
            ->and(wompi_spec_has('GET', '/Aplicativo/{idAplicativo}'))->toBeTrue();
    });

    it('gets the account on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->getAccount());

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/Cuenta')
            ->and(wompi_spec_has('GET', '/Cuenta'))->toBeTrue();
    });

    it('gets the regions on the documented endpoint', function () {
        $request = wompi_record(fn ($client) => $client->getRegions());

        expect($request['verb'])->toBe('GET')
            ->and($request['path'])->toBe('/api/Regiones')
            ->and(wompi_spec_has('GET', '/api/Regiones'))->toBeTrue();
    });

    it('never calls an endpoint Wompi does not document', function () {
        // The three endpoints v1 used that do not exist in the specification.
        expect(wompi_spec_has('POST', '/CargoRecurrente'))->toBeFalse()
            ->and(wompi_spec_has('POST', '/Transaccion'))->toBeFalse()
            ->and(wompi_spec_has('POST', '/TransaccionPrueba'))->toBeFalse()
            ->and(wompi_spec_has('GET', '/Tokenizacion/{tokenId}'))->toBeFalse();
    });
});
