# Testing con PEST - Laravel Wompi

Esta guía explica cómo probar el paquete Laravel Wompi (v2) con PEST y `Http::fake()`.

## Instalación

El paquete ya incluye PEST como dependencia de desarrollo. Si necesitas instalarlo en otro proyecto:

```bash
composer require pestphp/pest --dev --with-all-dependencies
```

## Comandos

```bash
composer test              # toda la suite
composer test:unit         # solo tests/Unit
composer test:feature      # solo tests/Feature
composer test:coverage     # reporte de cobertura
composer test:profile      # perfil de rendimiento
```

## ⚠️ Regla fundamental: la suite es offline

`tests/TestCase.php` activa `Http::preventStrayRequests()`. Si un `Http::fake()` **no coincide** con la petición, Laravel no devuelve una respuesta simulada: **realiza la llamada real** contra `api.wompi.sv`.

Para evitarlo hay dos reglas:

1. Usa comodines en los patrones que pueden llevar query string:

   ```php
   Http::fake([
       // Mal: la URL real es https://api.wompi.sv/Tokenizacion?Pagina=1&CantidadPagina=50
       'https://api.wompi.sv/Tokenizacion' => Http::response([...]),

       // Bien:
       'https://api.wompi.sv/Tokenizacion*' => Http::response([...]),
   ]);
   ```

2. En tus propias pruebas, llama a `Http::preventStrayRequests()` en el `setUp()`.

Con la protección activada, una petición sin fake lanza `StrayRequestException` en vez de salir a internet.

## Anatomía de un test

```php
use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\Facades\Wompi;

it('creates a payment link', function () {
    Http::fake([
        'https://id.wompi.sv/connect/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ], 200),
        'https://api.wompi.sv/EnlacePago' => Http::response([
            'idEnlace' => 1384854,
            'urlEnlace' => 'https://wompi.sv/pay/1384854',
            'estaProductivo' => true,
        ], 200),
    ]);

    $response = Wompi::createPaymentLink([
        'identificadorEnlaceComercio' => 'ORDER-1',
        'nombreProducto' => 'Test',
        'monto' => 100.00,
    ]);

    expect($response->idEnlace)->toBe(1384854);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/EnlacePago')
            && $request['monto'] === 100.00
            && $request->identificadorEnlaceComercio === 'ORDER-1';
    });
});
```

## Probar el webhook

El webhook de Wompi llega en **PascalCase**. Calcula la firma con el `client_secret`:

```php
use Illuminate\Support\Facades\Event;
use Rmirandasv\Wompi\Events\WompiPaymentProcessed;

it('processes a successful payment', function () {
    Event::fake();

    $payload = [
        'IdTransaccion' => 'txn_123',
        'Monto' => 250.00,
        'ResultadoTransaccion' => 'ExitosaAprobada',
        'CodigoAutorizacion' => 'AUTH123',
        'EsProductiva' => true,
        'EnlacePago' => [
            'Id' => 66,
            'IdentificadorEnlaceComercio' => 'ORDER-123',
        ],
    ];

    $body = json_encode($payload);
    $hash = hash_hmac('sha256', $body, config('wompi.client_secret'));

    $response = $this->postJson('/webhooks/wompi', $payload, ['wompi_hash' => $hash]);

    $response->assertStatus(200);

    Event::assertDispatched(WompiPaymentProcessed::class, function ($event) {
        return $event->isSuccessful === true
            && $event->webhook->reference() === 'ORDER-123';
    });
});
```

### Una transacción de prueba nunca marca una orden como pagada

```php
it('never marks a test transaction as paid', function () {
    Event::fake();

    $payload = [
        'IdTransaccion' => 'txn_test',
        'ResultadoTransaccion' => 'ExitosaAprobada',
        'EsProductiva' => false,   // fue una prueba, no se cobró
    ];

    $body = json_encode($payload);
    $hash = hash_hmac('sha256', $body, config('wompi.client_secret'));

    $this->postJson('/webhooks/wompi', $payload, ['wompi_hash' => $hash])
        ->assertStatus(200);

    Event::assertDispatched(WompiPaymentProcessed::class, fn ($event) => $event->isSuccessful === false);
});
```

## Probar el redirect

```php
it('validates the redirect signature', function () {
    $params = [
        'idTransaccion' => 'txn_123',
        'monto' => '100.00',
        'esReal' => 'True',
        'formaPago' => 'TarjetaCreditoDebito',
        'esAprobada' => 'True',
        'codigoAutorizacion' => 'AUTH789',
        'mensaje' => 'Transaccion Aprobada',
    ];

    $hash = hash_hmac('sha256', implode('', array_values($params)), config('wompi.client_secret'));

    $response = $this->get('/payments/callback?'.http_build_query($params).'&hash='.$hash);

    $response->assertStatus(200);
});
```

## Probar errores del API

Wompi responde con la forma `ApiErrorResult`:

```php
use Rmirandasv\Wompi\Exceptions\WompiApiException;

it('maps the api errors', function () {
    Http::fake([
        'https://id.wompi.sv/connect/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ], 200),
        'https://api.wompi.sv/EnlacePago' => Http::response([
            'servicioError' => 'EnlacePago',
            'subTipoError' => 'DATOS_INVALIDOS',
            'mensajes' => ['El monto debe ser mayor a 0'],
        ], 400),
    ]);

    try {
        Wompi::createPaymentLink(['identificadorEnlaceComercio' => 'ORDER-1', 'nombreProducto' => 'Test']);
    } catch (WompiApiException $e) {
        expect($e->getStatusCode())->toBe(400)
            ->and($e->getMessages())->toBe(['El monto debe ser mayor a 0'])
            ->and($e->getServiceError())->toBe('EnlacePago');
    }
});
```

## El test de contrato contra el spec de Wompi

`tests/Fixtures/wompi-swagger.json` es una copia recortada de la especificación OpenAPI de Wompi (`https://api.wompi.sv/swagger/v1/swagger.json`).

`tests/Unit/WompiEndpointContractTest.php` verifica **dos cosas** para cada método del cliente:

1. Que el cliente llama a ese verbo y esa ruta.
2. Que Wompi documenta ese verbo y esa ruta.

La segunda comprobación es la que evita la clase de bug que rompió la v1: un endpoint renombrado o eliminado falla en CI en lugar de devolver un 404 en producción.

El workflow **"Refresh Wompi spec"** (`.github/workflows/wompi-spec.yml`) corre cada semana, descarga el spec vigente y falla si cambió, adjuntando la nueva versión como artefacto.

Para actualizar el fixture a mano:

```bash
curl -fsSL https://api.wompi.sv/swagger/v1/swagger.json | jq '{
  openapi: .openapi,
  info: {"title": .info.title, "version": .info.version},
  "x-source": "https://api.wompi.sv/swagger/v1/swagger.json",
  "x-fetched-at": "'$(date -u +%Y-%m-%d)'",
  paths: (.paths | with_entries(.value |= with_entries(.value |= {
    summary: (.summary // null),
    parameters: ((.parameters // []) | map({name, in, required}))
  })))
}' > tests/Fixtures/wompi-swagger.json
```

## Probar que no se filtran datos de tarjeta

```php
it('never logs the card number', function () {
    // Usa un logger que grabe en memoria (ver tests/Unit/LoggingSecurityTest.php)
    // y comprueba que el PAN no aparece en ningún log.
});
```

La suite del paquete ya cubre esto en `tests/Unit/LoggingSecurityTest.php` y `tests/Unit/SensitiveDataMaskerTest.php`.

## Mockear el cliente completo

```php
$mock = Mockery::mock(\Rmirandasv\Wompi\Contracts\WompiClientInterface::class);
$mock->shouldReceive('createPaymentLink')->andReturn(
    new \Rmirandasv\Wompi\DTOs\Responses\PaymentLinkResponseDTO(['idEnlace' => 1])
);

$this->app->instance(\Rmirandasv\Wompi\Contracts\WompiClientInterface::class, $mock);
```

## Ejemplos completos

Consulta [examples/PaymentControllerTest.php](examples/PaymentControllerTest.php) para ejemplos de controladores, webhooks, suscripciones y manejo de errores.
