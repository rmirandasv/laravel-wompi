# Testing con PEST - Laravel Wompi

Esta guía explica cómo probar el paquete Laravel Wompi utilizando PEST con mocks.

## Instalación

El paquete ya incluye PEST como dependencia de desarrollo. Si necesitas instalarlo en otro proyecto:

```bash
composer require pestphp/pest --dev --with-all-dependencies
composer require orchestra/testbench --dev
composer require mockery/mockery --dev
./vendor/bin/pest --init
```

## Ejecutar Tests

```bash
# Ejecutar todos los tests
./vendor/bin/pest

# Ejecutar tests específicos
./vendor/bin/pest tests/Unit/WompiClientAuthenticationTest.php

# Ejecutar con coverage
./vendor/bin/pest --coverage

# Ejecutar en modo verboso
./vendor/bin/pest --verbose
```

## Estructura de Tests

```
tests/
├── Pest.php                              # Configuración de PEST
├── TestCase.php                          # TestCase base con Orchestra Testbench
├── Unit/
│   ├── WompiClientAuthenticationTest.php # Tests de autenticación
│   ├── WompiClientApiTest.php           # Tests de llamadas API
│   ├── WompiClientWebhookTest.php       # Tests de webhooks
│   └── WompiClientRedirectTest.php      # Tests de validación de redirect
└── Feature/
    └── PaymentFlowTest.php              # Tests de flujos completos
```

## Mocking con PEST

### 1. Mock de HTTP Requests (Facade Http)

PEST utiliza los facades de Laravel para hacer mocks de HTTP requests:

```php
use Illuminate\Support\Facades\Http;

it('can create a payment link', function () {
    // Mock de las respuestas HTTP
    Http::fake([
        'https://id.wompi.sv/test' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ], 200),
        'https://api.wompi.sv/v1/test/EnlacePago' => Http::response([
            'id' => 'link_123',
            'url' => 'https://wompi.sv/pay/link_123',
        ], 201),
    ]);

    $client = app(WompiClient::class);
    $result = $client->createPaymentLink([
        'monto' => 100.00,
        'descripcion' => 'Test Payment',
    ]);

    // Assertions
    expect($result)
        ->toHaveKey('id')
        ->and($result['id'])->toBe('link_123');

    // Verificar que se hizo la llamada correcta
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/EnlacePago') &&
               $request['monto'] === 100.00;
    });
});
```

### 2. Mock de Cache

```php
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush(); // Limpiar cache antes de cada test
});

it('caches access token', function () {
    Http::fake([
        'https://id.wompi.sv/test' => Http::response([
            'access_token' => 'cached_token',
            'expires_in' => 3600,
        ], 200),
        '*' => Http::response(['data' => 'test'], 200),
    ]);

    $client = app(WompiClient::class);
    
    // Primera llamada - debe autenticar
    $client->getAplicativoData();
    
    // Segunda llamada - usa token cacheado
    $client->getAplicativoData();

    // Verificar que la autenticación solo se llamó una vez
    Http::assertSent(function ($request) {
        return $request->url() === 'https://id.wompi.sv/test';
    }, 1);
});
```

### 3. Mock de Requests (Webhooks)

```php
use Illuminate\Http\Request;

it('validates webhook request', function () {
    $webhookPayload = [
        'idTransaccion' => 'txn_123',
        'monto' => 100.00,
        'resultadoTransaccion' => 'ExitosaAprobada',
    ];

    $webhookBody = json_encode($webhookPayload);
    $wompiHash = hash_hmac('sha256', $webhookBody, 'test_client_secret');

    // Crear un Request mock
    $request = Request::create(
        '/webhook',
        'POST',
        [],
        [],
        [],
        ['HTTP_WOMPI_HASH' => $wompiHash],
        $webhookBody
    );

    $client = app(WompiClient::class);
    $result = $client->validateWebhookRequest($request);

    expect($result)
        ->toBeArray()
        ->toHaveKey('idTransaccion');
});
```

### 4. Usar Http::sequence() para Múltiples Respuestas

```php
it('handles multiple API calls', function () {
    Http::fake([
        'https://api.wompi.sv/v1/test/Tokenizacion/tok_123' => Http::sequence()
            ->push(['tokenId' => 'tok_123', 'status' => 'active'], 200)
            ->push(['success' => true, 'message' => 'Deleted'], 200),
    ]);

    $client = app(WompiClient::class);
    
    // Primera llamada - devuelve el token
    $token = $client->getTokenizedCard('tok_123');
    expect($token['status'])->toBe('active');
    
    // Segunda llamada - devuelve confirmación de eliminación
    $result = $client->deleteTokenizedCard('tok_123');
    expect($result['success'])->toBeTrue();
});
```

## Ejemplos de Assertions con PEST

### Expectations Básicas

```php
// Valores
expect($value)->toBe(100);
expect($value)->toEqual(100.00); // Comparación flexible
expect($value)->toBeTrue();
expect($value)->toBeFalse();
expect($value)->toBeNull();

// Arrays
expect($array)->toBeArray();
expect($array)->toHaveKey('id');
expect($array)->toHaveKeys(['id', 'name']);
expect($array)->toHaveCount(5);

// Strings
expect($string)->toBeString();
expect($string)->toContain('test');
expect($string)->toStartWith('http');
expect($string)->toEndWith('.com');

// Números
expect($number)->toBeGreaterThan(10);
expect($number)->toBeLessThan(100);
expect($number)->toBeBetween(10, 100);
```

### Expectations Encadenadas

```php
expect($result)
    ->toBeArray()
    ->toHaveKey('id')
    ->and($result['id'])->toBe('txn_123')
    ->and($result['amount'])->toBeGreaterThan(0);
```

### Excepciones

```php
it('throws exception when credentials are missing', function () {
    new WompiClient(null, 'https://api.test', 'client_id', 'secret');
})->throws(ConfigurationException::class, 'Wompi credentials are not set');
```

## Hooks de PEST

### beforeEach / afterEach

```php
describe('WompiClient Tests', function () {
    beforeEach(function () {
        Cache::flush();
        // Setup que se ejecuta antes de cada test
    });

    afterEach(function () {
        // Cleanup que se ejecuta después de cada test
    });

    it('does something', function () {
        // Test code
    });
});
```

## Verificaciones de HTTP

```php
// Verificar que se envió una request
Http::assertSent(function ($request) {
    return $request->url() === 'https://api.example.com' &&
           $request->hasHeader('Authorization');
});

// Verificar cantidad de requests
Http::assertSentCount(3);

// Verificar que NO se envió una request
Http::assertNotSent(function ($request) {
    return str_contains($request->url(), '/forbidden');
});

// Verificar que no se enviaron requests
Http::assertNothingSent();
```

## Estructura de un Test

```php
<?php

use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\WompiClient;

describe('Feature Name', function () {
    
    beforeEach(function () {
        // Setup común para todos los tests
    });

    it('does something specific', function () {
        // Arrange: Preparar el escenario
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        // Act: Ejecutar la acción
        $client = app(WompiClient::class);
        $result = $client->someMethod();

        // Assert: Verificar el resultado
        expect($result)->toBeArray();
    });

    it('handles errors correctly', function () {
        // Test de manejo de errores
    })->throws(ExceptionClass::class);
});
```

## Best Practices

1. **Un test, una cosa**: Cada test debe verificar un comportamiento específico
2. **Nombres descriptivos**: Usa `it('should do something')` o `it('does something')`
3. **Arrange-Act-Assert**: Organiza tu código en estas tres secciones
4. **Limpia el estado**: Usa `beforeEach()` para resetear estado compartido
5. **Mock solo lo necesario**: No mockees todo, solo las dependencias externas
6. **Tests independientes**: Cada test debe poder ejecutarse solo

## Testing de Webhooks

```php
it('validates webhook signature', function () {
    $client = app(WompiClient::class);
    
    $body = json_encode(['id' => 'txn_123']);
    $hash = hash_hmac('sha256', $body, 'test_client_secret');
    
    $isValid = $client->validateWebhookSignature($body, $hash);
    
    expect($isValid)->toBeTrue();
});
```

## Testing de Flujos Completos

```php
it('can complete payment flow', function () {
    Http::fake([
        'https://id.wompi.sv/test' => Http::response([
            'access_token' => 'token',
            'expires_in' => 3600,
        ], 200),
        'https://api.wompi.sv/v1/test/EnlacePago' => Http::response([
            'id' => 'link_123',
            'url' => 'https://wompi.sv/pay/link_123',
        ], 201),
    ]);

    $client = app(WompiClient::class);
    
    // Crear link de pago
    $link = $client->createPaymentLink([
        'monto' => 100.00,
        'descripcion' => 'Test',
    ]);
    
    expect($link)->toHaveKey('url');
    
    // Verificar el flujo
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/EnlacePago');
    });
});
```

## Recursos Adicionales

- [Documentación de PEST](https://pestphp.com)
- [Orchestra Testbench](https://packages.tools/testbench)
- [Laravel HTTP Tests](https://laravel.com/docs/http-tests)
- [Mockery Documentation](http://docs.mockery.io)

## Comandos Útiles

```bash
# Ejecutar solo tests de Unit
./vendor/bin/pest tests/Unit

# Ejecutar solo tests de Feature
./vendor/bin/pest tests/Feature

# Ejecutar con filtro
./vendor/bin/pest --filter="authentication"

# Ver tests disponibles sin ejecutarlos
./vendor/bin/pest --list-tests

# Ejecutar en modo parallel (más rápido)
./vendor/bin/pest --parallel

# Generar reporte de coverage en HTML
./vendor/bin/pest --coverage --coverage-html=coverage
```

Si este documento fue util para ti, deja una estrella en el repositorio!