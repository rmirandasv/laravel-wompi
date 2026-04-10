# Laravel Wompi

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-8892BF.svg)](https://php.net)
[![PHP 8.4](https://img.shields.io/badge/php-8.4-blue.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-10%2B%7C11%2B%7C12%2B-FF2D20.svg)](https://laravel.com)

Un paquete de Laravel completo y robusto para integrar la pasarela de pago [Wompi](https://wompi.sv) de El Salvador en tus aplicaciones. Soporte oficial para PHP 8.2, 8.3, 8.4 y 8.5.

## 📋 Características

- ✅ **Enlace de Pago** - Genera URLs y códigos QR para pagos
- ✅ **Transacciones 3DS** - Soporte completo para pagos con 3D Secure
- ✅ **Tokenización** - Almacena tarjetas de forma segura para uso futuro
- ✅ **Cargos Recurrentes** - Procesa pagos recurrentes con tarjetas tokenizadas
- ✅ **Validación de Webhooks** - Verificación HMAC de notificaciones
- ✅ **Validación de Redirects** - Valida parámetros de URL de retorno
- ✅ **Datos del Aplicativo** - Consulta configuración y capacidades
- ✅ **Transacciones de Prueba** - Entorno de desarrollo/testing
- ✅ **Autenticación OAuth2** - Gestión automática de tokens con caché
- ✅ **Facade Laravel** - Interfaz limpia y expresiva

## 📦 Instalación

Instala el paquete vía Composer:

```bash
composer require rmirandasv/laravel-wompi
```

El paquete se auto-registra automáticamente gracias al auto-discovery de Laravel.

### Publicar Configuración (Opcional)

```bash
php artisan vendor:publish --tag="wompi-config"
```

Esto creará el archivo `config/wompi.php` en tu aplicación.

## ⚙️ Configuración

Agrega las siguientes variables a tu archivo `.env`:

```env
WOMPI_AUTH_URL=https://id.wompi.sv
WOMPI_API_URL=https://api.wompi.sv/v1
WOMPI_CLIENT_ID=tu_client_id
WOMPI_CLIENT_SECRET=tu_client_secret
WOMPI_WEBHOOK_SECRET=tu_webhook_secret
```

> 💡 **Nota**: Obtén tus credenciales desde tu dashboard de Wompi.

## 🚀 Uso

### Crear Enlace de Pago

Genera un enlace de pago (Payment Link) para que tus clientes realicen pagos:

```php
use Rmirandasv\Wompi\Facades\Wompi;

$response = Wompi::createPaymentLink([
    'identificadorEnlaceComercio' => 'ORDER-' . uniqid(),
    'monto' => 100.00,
    'nombreProducto' => 'Mi Producto',
    'formaPago' => [
        'permitirTarjetaCreditoDebido' => true,
        'permitirPagoConPuntoAgricola' => true,
        'permitirPagoEnCuotasAgricola' => false,
    ],
    'configuracion' => [
        'urlRedirect' => 'https://tudominio.com/payment/success',
        'urlRetorno' => 'https://tudominio.com/cart',
        'emailsNotificacion' => '[email protected]',
        'urlWebhook' => 'https://tudominio.com/webhooks/wompi',
        'notificarTransaccionCliente' => true,
    ],
]);

// Respuesta
$paymentUrl = $response['urlEnlace'];        // URL del enlace de pago
$qrCodeUrl = $response['urlQrCodeEnlace'];   // URL del código QR
$paymentId = $response['idEnlace'];          // ID del enlace
$isProduction = $response['estaProductivo']; // true/false

// Redirigir al usuario al enlace de pago
return redirect($paymentUrl);
```

### Crear Transacción 3DS

Procesa una transacción directa con 3D Secure:

```php
$response = Wompi::createTransaction3DS([
    'monto' => 100.00,
    'tarjeta' => [
        'numeroTarjeta' => '4111111111111111',
        'mesExpiracion' => '12',
        'anioExpiracion' => '2025',
        'cvv' => '123',
        'nombreTarjeta' => 'JUAN PEREZ',
    ],
    'urlRedirect' => 'https://tudominio.com/payment/success',
    'identificadorTransaccionComercio' => 'TXN-' . uniqid(),
]);

// Respuesta
$transactionId = $response['idTransaccion'];
$url3DS = $response['urlCompletarPago3Ds']; // URL para completar 3DS

// Redirigir al cliente a la URL de 3DS
return redirect($url3DS);
```

### Tokenización de Tarjetas

Guarda tarjetas de forma segura para pagos futuros:

```php
// Tokenizar una tarjeta
$response = Wompi::tokenizeCard([
    'numeroTarjeta' => '4111111111111111',
    'mesExpiracion' => '12',
    'anioExpiracion' => '2025',
    'cvv' => '123',
    'nombreTarjeta' => 'JUAN PEREZ',
    'identificadorCliente' => 'CUSTOMER-123',
]);

$tokenId = $response['idToken'];

// Obtener información de un token
$tokenInfo = Wompi::getTokenizedCard($tokenId);

// Eliminar un token
Wompi::deleteTokenizedCard($tokenId);
```

### Cargos Recurrentes

Realiza cargos con tarjetas previamente tokenizadas:

```php
$response = Wompi::createRecurringCharge([
    'idToken' => 'token_abc123',
    'monto' => 50.00,
    'identificadorTransaccionComercio' => 'RECURRING-' . uniqid(),
    'descripcion' => 'Suscripción Mensual',
]);

$transactionId = $response['idTransaccion'];
$isApproved = $response['esAprobada'] ?? false;
```

### Consultar Datos del Aplicativo

Obtén información sobre las capacidades de tu aplicativo:

```php
$aplicativoData = Wompi::getAplicativoData();

// Información disponible:
$soportaPuntos = $aplicativoData['soportaPagoConPuntos'];
$soportaCuotas = $aplicativoData['soportaPagoEnCuotas'];
$cuotasDisponibles = $aplicativoData['cuotasDisponibles'];
```

## 🔔 Webhooks y Eventos

Los webhooks son la forma principal de recibir notificaciones de transacciones exitosas. El paquete ofrece múltiples formas de manejar estas notificaciones: a través de **Eventos de Laravel** o usando tu propio controlador.

### 1. Usar Eventos de Laravel (Recomendado)

El paquete despacha eventos automáticamente cuando valida un webhook exitosamente. Puedes escucharlos en tu aplicación:

- `Rmirandasv\Wompi\Events\WompiWebhookReceived`: Se dispara siempre que se recibe y valida una notificación de Wompi, útil para guardar logs en crudo.
- `Rmirandasv\Wompi\Events\WompiPaymentProcessed`: Se dispara junto al anterior, pero incluye la propiedad `$isSuccessful` (booleano) facilitando saber si el pago fue aprobado o no.

**Ejemplo de Listener:**

```php
namespace App\Listeners;

use Rmirandasv\Wompi\Events\WompiPaymentProcessed;

class ProcessWompiPayment
{
    public function handle(WompiPaymentProcessed $event): void
    {
        $payload = $event->payload;
        $orderId = $payload['enlacePago']['identificadorEnlaceComercio'] ?? null;
        
        if ($event->isSuccessful) {
            // Lógica para marcar orden como pagada...
            \Log::info("Pago exitoso procesado para la orden: {$orderId}");
        } else {
            // Lógica para marcar orden como fallida...
        }
    }
}
```

### 2. Middleware para validación

Si decides crear tus propios endpoints y controladores para recibir los webhooks y no quieres llamar al método de validación manualmente, puedes usar el middleware incluido. 

Primero aségurate de que tu ruta esté excluida del middleware CSRF y usa el alias `wompi.webhook`:

```php
Route::post('/webhooks/wompi', [WompiWebhookController::class, 'handle'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('wompi.webhook'); // <-- Validación automática de firma HMAC
```

### 3. Crear tu Controlador Manual

Si prefieres manejar la validación manualmente dentro de tu controlador, puedes hacerlo así:

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Rmirandasv\Wompi\Facades\Wompi;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;

class WompiWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        try {
            // Validar y obtener datos del webhook (esto también dispara los eventos de arriba)
            $webhookData = Wompi::validateWebhookRequest($request);
            
            // Verificar si es un pago exitoso manualmente
            if (Wompi::isSuccessfulPayment($webhookData)) {
                $this->processSuccessfulPayment($webhookData);
            }
            
            // Siempre retornar 200 OK
            return response()->json(['status' => 'ok'], 200);
            
        } catch (PaymentGatewayException $e) {
            // Log del error pero retornar 200 para evitar reintentos
            \Log::error('Webhook validation failed: ' . $e->getMessage());
            return response()->json(['status' => 'ok'], 200);
        }
    }
    
    private function processSuccessfulPayment(array $data): void
    {
        $orderId = $data['enlacePago']['identificadorEnlaceComercio'];
        $transactionId = $data['idTransaccion'];
        $amount = $data['monto'];
        
        // Actualizar tu orden en la base de datos
        $order = Order::where('reference', $orderId)->first();
        
        if ($order && !$order->is_paid) {
            $order->update([
                'status' => 'paid',
                'transaction_id' => $transactionId,
                'payment_method' => $data['formaPagoUtilizada'],
                'paid_at' => now(),
            ]);
            
            // Disparar eventos, enviar emails, etc.
            event(new OrderPaid($order));
        }
    }
}
```

### 2. Registrar tu Ruta

En `routes/api.php` o `routes/web.php`:

```php
use App\Http\Controllers\WompiWebhookController;

Route::post('/webhooks/wompi', [WompiWebhookController::class, 'handle'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
```

### 3. Configurar en Wompi Dashboard

Configura tu webhook URL en el dashboard de Wompi:

```
https://tudominio.com/webhooks/wompi
```

### Datos Disponibles en el Webhook

```php
$webhookData = [
    'idCuenta' => 'string',
    'fechaTransaccion' => 'string',
    'monto' => 100.00,
    'moduloUtilizado' => 'EnlacePago',
    'formaPagoUtilizada' => 'TarjetaCreditoDebito',
    'idTransaccion' => 'TXN123',
    'resultadoTransaccion' => 'ExitosaAprobada',
    'codigoAutorizacion' => 'AUTH123',
    'idIntentoPago' => 'string',
    'cantidad' => 1,
    'esProductiva' => true,
    'aplicativo' => [...],
    'enlacePago' => [
        'idEnlace' => 123,
        'identificadorEnlaceComercio' => 'ORDER-123',
        'nombreProducto' => 'Mi Producto',
    ],
    'cliente' => [
        'nombre' => 'Juan Pérez',
        'email' => '[email protected]',
        ...
    ],
];
```

## 🔄 Validación de Redirect URLs

Cuando el usuario regresa de Wompi, valida los parámetros:

```php
use Rmirandasv\Wompi\Facades\Wompi;

public function paymentReturn(Request $request)
{
    $params = $request->all();
    $receivedHash = $request->input('hash');
    
    // Validar la firma
    if (Wompi::validateRedirectParams($params, $receivedHash)) {
        if ($params['esAprobada'] === 'true') {
            // Pago aprobado
            return view('payment.success', [
                'transactionId' => $params['idTransaccion'],
            ]);
        } else {
            // Pago rechazado
            return view('payment.failed', [
                'message' => $params['mensaje'],
            ]);
        }
    }
    
    // Firma inválida
    abort(403, 'Invalid signature');
}
```

## 🧪 Transacciones de Prueba

Para desarrollo y testing:

```php
$response = Wompi::executeTestTransaction([
    'monto' => 100.00,
    'resultadoDeseado' => 'Aprobada', // o 'Rechazada'
    'identificadorTransaccionComercio' => 'TEST-' . uniqid(),
]);
```

## 🔧 Inyección de Dependencias

También puedes usar inyección de dependencias en lugar de la Facade. El paquete expone un contrato (`Interface`) para facilitar el testing y desacoplar tu código:

```php
use Rmirandasv\Wompi\Contracts\WompiClientInterface;
use Rmirandasv\Wompi\DTOs\Requests\PaymentLinkRequestDTO;

class PaymentService
{
    public function __construct(private WompiClientInterface $wompi)
    {
    }
    
    public function createPayment(array $data)
    {
        // Puedes pasar un array tradicional o usar los nuevos DTOs para mayor seguridad:
        // $dto = PaymentLinkRequestDTO::fromArray($data);
        return $this->wompi->createPaymentLink($data);
    }
}
```

## 🛡️ Manejo de Excepciones

El paquete lanza excepciones específicas para diferentes escenarios:

```php
use Rmirandasv\Wompi\Exceptions\ConfigurationException;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;
use Rmirandasv\Wompi\Exceptions\WompiValidationException;

try {
    $response = Wompi::createPaymentLink($data);
} catch (WompiValidationException $e) {
    // Los datos proporcionados en los DTOs no son válidos
    \Log::error('Validation error: ' . $e->getMessage(), $e->getErrors());
} catch (ConfigurationException $e) {
    // Credenciales no configuradas correctamente
    \Log::error('Wompi configuration error: ' . $e->getMessage());
} catch (PaymentGatewayException $e) {
    // Error comunicándose con la API de Wompi (e.g. 400 Bad Request, 401 Unauthorized)
    \Log::error('Wompi API error: ' . $e->getMessage());
    
    // Puedes acceder a los detalles del error devueltos por Wompi
    $statusCode = $e->getStatusCode();
    $wompiErrorBody = $e->getResponseBody();
}
```

## 📚 Métodos Disponibles

| Método | Descripción |
|--------|-------------|
| `createPaymentLink(array\|PaymentLinkRequestDTO $data)` | Crea un enlace de pago |
| `createTransaction3DS(array\|Transaction3DSRequestDTO $data)` | Crea una transacción con 3DS |
| `tokenizeCard(array\|TokenizeCardRequestDTO $data)` | Tokeniza una tarjeta |
| `getTokenizedCard(string $tokenId)` | Obtiene información de un token |
| `deleteTokenizedCard(string $tokenId)` | Elimina un token |
| `createRecurringCharge(array $data)` | Crea un cargo recurrente |
| `getAplicativoData()` | Obtiene datos del aplicativo |
| `executeTestTransaction(array $data)` | Ejecuta transacción de prueba |
| `validateWebhookRequest(Request $request)` | Valida un webhook |
| `validateWebhookSignature(string $body, string $hash)` | Valida firma HMAC |
| `validateRedirectParams(array $params, string $hash)` | Valida parámetros de redirect |
| `isSuccessfulPayment(array $webhookData)` | Verifica si es pago exitoso |

## 🧪 Testing

Este paquete viene completamente testeado usando **[PEST PHP](https://pestphp.com)**. Incluye 31 tests que cubren todas las funcionalidades.

### Ejecutar Tests

```bash
# Ejecutar todos los tests
composer test

# Ejecutar solo tests unitarios
composer test:unit

# Ejecutar solo tests de integración
composer test:feature

# Ver perfil de rendimiento
composer test:profile

# Generar reporte de cobertura
composer test:coverage
```

### Testing en tu Aplicación

Puedes hacer mock del cliente Wompi en tus tests usando Laravel HTTP Fakes:

```php
use Illuminate\Support\Facades\Http;

it('creates a payment successfully', function () {
    Http::fake([
        'https://id.wompi.sv/*' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ], 200),
        'https://api.wompi.sv/*/EnlacePago' => Http::response([
            'id' => 'payment_123',
            'url' => 'https://wompi.sv/pay/payment_123',
        ], 201),
    ]);

    $result = app(WompiClient::class)->createPaymentLink([
        'monto' => 100.00,
        'descripcion' => 'Test',
    ]);

    expect($result)->toHaveKey('url');
});
```

Para más ejemplos y guías completas, consulta:
- [TESTING.md](TESTING.md) - Guía completa de testing con PEST
- [examples/PaymentControllerTest.php](examples/PaymentControllerTest.php) - Ejemplos de tests de controladores

## 📖 Documentación Oficial

Para más detalles sobre los parámetros y respuestas de cada endpoint, consulta la documentación oficial:

- [Documentación Wompi](https://docs.wompi.sv/)
- [Crear Enlace de Pago](https://docs.wompi.sv/metodos-api/enlace-de-pago)
- [Transacciones 3DS](https://docs.wompi.sv/metodos-api/crear-transaccion-compra-3ds)
- [Tokenización](https://docs.wompi.sv/metodos-api/tokenizacion)
- [Cargos Recurrentes](https://docs.wompi.sv/metodos-api/cargos-recurrentes)
- [Webhooks](https://docs.wompi.sv/webhook/definicion-webhook)

## 🤖 Integración con IA (Prompt-to-Code)

Este paquete incluye una **Skill/Regla de IA** diseñada para que puedas integrar pagos en minutos usando asistentes como **Cursor, Windsurf, Claude Dev o Gemini CLI**.

La skill proporciona el contexto técnico necesario para que la IA genere controladores, rutas y validaciones siguiendo las mejores prácticas de seguridad de Wompi.

### Cómo usar la Skill:
1.  **Cursor/Windsurf**: Copia el archivo `laravel-wompi-skill/SKILL.md` a la carpeta `.cursor/rules/wompi.md` (o similar) en tu proyecto.
2.  **Gemini CLI**: Importa la skill o proporciónale el archivo como contexto.
3.  **Prompt**: Pídele a tu IA: *"Integra checkout con Wompi: enlace de pago, retorno con validación de hash y webhook usando eventos; utiliza rmirandasv/laravel-wompi >= 1.0.1"*.

Encuentra la guía detallada en [laravel-wompi-skill/SKILL.md](laravel-wompi-skill/SKILL.md).

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este paquete es software de código abierto licenciado bajo la [Licencia MIT](LICENSE).

## 👨‍💻 Autor

**Ronald Miranda**
- GitHub: [@rmirandasv](https://github.com/rmirandasv)

## 🙏 Agradecimientos

- [Wompi](https://wompi.sv) por su API de pagos
- La comunidad de Laravel

---

**⚠️ Nota de Seguridad**: Nunca compartas tus credenciales de Wompi. Mantenlas seguras en variables de entorno y nunca las commits a control de versiones.

