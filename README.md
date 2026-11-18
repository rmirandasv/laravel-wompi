# Laravel Wompi

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2.35%20%7C%20%5E8.3.36%20%7C%20%5E8.4.27%20%7C%20%5E8.5.12-8892BF.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-10%2B%7C11%2B%7C12%2B%7C13%2B-FF2D20.svg)](https://laravel.com)

Un paquete de Laravel para integrar la pasarela de pago [Wompi](https://wompi.sv) de El Salvador en tus aplicaciones.

> [!IMPORTANT]
> **v2.0 es un cambio incompatible.** En v1 todos los endpoints del API estaban rotos (ver [UPGRADE.md](UPGRADE.md)). Si vienes de v1, lee [UPGRADE.md](UPGRADE.md) antes de actualizar.

## 📋 Características

- ✅ **Enlace de Pago** — Crea, consulta, actualiza, activa, desactiva y personaliza enlaces de pago
- ✅ **Transacciones 3DS** — Pagos con 3D Secure (datos del cliente y de la tarjeta anidados como los exige Wompi)
- ✅ **Cargos tokenizados** — Cobra una tarjeta tokenizada sin 3D Secure
- ✅ **Tokenización** — Guarda tarjetas de forma segura para uso futuro
- ✅ **Suscripciones** — Enlaces de pago recurrentes (`EnlacePagoRecurrente`) y consulta de suscriptores
- ✅ **Consulta de transacciones** — Detalle, listado, comprobante y estadísticas diarias
- ✅ **Webhooks** — Verificación HMAC, normalización del payload y eventos de Laravel
- ✅ **Validación de Redirect** — Verifica los parámetros de la URL de retorno
- ✅ **Ruta publicable** — Endpoint de webhook listo para usar con `vendor:publish`
- ✅ **Autenticación OAuth2** — Token cacheado con lock, margen de expiración y recuperación ante 401
- ✅ **Seguro por diseño** — PAN y CVV nunca llegan a los logs

## 📦 Instalación

```bash
composer require rmirandasv/laravel-wompi
```

El paquete se auto-registra gracias al auto-discovery de Laravel.

### Publicar la configuración (opcional)

```bash
php artisan vendor:publish --tag="wompi-config"
```

### Publicar la ruta y el controlador del webhook (recomendado)

```bash
php artisan vendor:publish --tag="wompi-webhook"
```

Esto crea `routes/wompi.php` y `app/Http/Controllers/WompiWebhookController.php`.

## ⚙️ Configuración

```env
WOMPI_API_URL=https://api.wompi.sv
WOMPI_AUTH_URL=https://id.wompi.sv/connect/token
WOMPI_CLIENT_ID=tu_client_id
WOMPI_CLIENT_SECRET=tu_client_secret
```

> [!WARNING]
> `WOMPI_API_URL` **no** lleva `/v1`. El prefijo `/v1` que aparece en el Swagger es la versión del documento OpenAPI, no del API: `https://api.wompi.sv/v1/EnlacePago` devuelve **404**.
>
> `WOMPI_AUTH_URL` apunta al endpoint OAuth2 `client_credentials`. La raíz `https://id.wompi.sv` devuelve una página HTML de login, no un token.

El `client_id` es el **App ID** y el `client_secret` es el **API Secret** de tu negocio en el [panel de Wompi](https://panel.wompi.sv/).

El mismo `client_secret` es la llave HMAC para validar webhooks y URLs de redirect: Wompi emite un solo secreto por aplicativo, por eso no existe un `WOMPI_WEBHOOK_SECRET` aparte.

### Opciones adicionales

```env
WOMPI_TIMEOUT=30
WOMPI_CONNECT_TIMEOUT=10
WOMPI_RETRY=2
WOMPI_TOKEN_TTL_MARGIN=60
WOMPI_LOG_PAYLOADS=false
WOMPI_WEBHOOK_PATH=webhooks/wompi
```

## 🚀 Uso

### Crear enlace de pago

```php
use Rmirandasv\Wompi\Facades\Wompi;

$response = Wompi::createPaymentLink([
    'identificadorEnlaceComercio' => 'ORDER-' . $order->uuid,   // tu id de orden
    'nombreProducto' => 'Camisa azul',
    'monto' => 25.50,
    'formaPago' => [
        'permitirTarjetaCreditoDebido' => true,
        'permitirPagoConPuntoAgricola' => true,
        'permitirPagoEnCuotasAgricola' => false,
    ],
    'configuracion' => [
        'urlRedirect' => route('payment.return'),
        'urlRetorno' => route('cart.index'),
        'urlWebhook' => route('wompi.webhook'),
        'emailsNotificacion' => 'pagos@tiapp.com',
        'notificarTransaccionCliente' => true,
    ],
]);

return redirect($response->urlEnlace);
```

El `identificadorEnlaceComercio` es tu **clave idempotente**: úsalo para relacionar el webhook con tu orden.

También puedes usar el DTO, que valida los datos antes de llamar al API:

```php
use Rmirandasv\Wompi\DTOs\Requests\PaymentLinkRequestDTO;

$response = Wompi::createPaymentLink(PaymentLinkRequestDTO::fromArray([...]));
```

### Transacción 3DS

Wompi exige la tarjeta dentro de `tarjetaCreditoDebido` y los datos del cliente:

```php
$response = Wompi::createTransaction3DS([
    'monto' => 25.50,
    'tarjetaCreditoDebido' => [
        'numeroTarjeta' => '4111111111111111',
        'cvv' => '123',
        'mesVencimiento' => 12,
        'anioVencimiento' => 2030,
        'nombreEnTarjeta' => 'JUAN PEREZ',
    ],
    'urlRedirect' => route('payment.return'),
    'nombre' => 'JUAN',
    'apellido' => 'PEREZ',
    'email' => 'juan@example.com',
    'ciudad' => 'SAN SALVADOR',
    'direccion' => 'Calle 1 #2-3',
    'idPais' => 'SV',
    'idRegion' => 'SV-SS',
    'codigoPostal' => '1101',
    'telefono' => '70000000',
]);

return redirect($response->urlCompletarPago3Ds);
```

Los valores de `idPais` e `idRegion` los puedes obtener de `Wompi::getRegions()`.

### Suscripciones (enlace de pago recurrente)

Este es el mecanismo de suscripción de Wompi: tú creas el plan y el cliente se afilia solo al `urlEnlace` que devuelve Wompi.

```php
$subscription = Wompi::createRecurringPaymentLink([
    'diaDePago' => 5,                 // día del mes en que se cobra
    'nombre' => 'Plan mensual',
    'monto' => 29.99,
    'descripcionProducto' => 'Suscripción al plan mensual',
]);

// Compartir $subscription->urlEnlace con el cliente
// o $subscription->urlQrCodeEnlace para un código QR
```

Para ver quién está suscrito:

```php
$subscriptions = Wompi::listRecurringSubscriptions($subscription->idEnlace);

foreach ($subscriptions->items() as $subscriber) {
    $subscriber['idSuscriptor'];
    $subscriber['pagosRealizados'];
    $subscriber['estado'];
}
```

> [!NOTE]
> Si Wompi responde `403` al crear el enlace recurrente, el servicio de suscripciones debe estar habilitado para tu negocio: hay que solicitarlo a su soporte.

Si en cambio ya **tienes** una tarjeta tokenizada y quieres cobrarla periódicamente, usa el cargo tokenizado (no necesita habilitación especial):

```php
$charge = Wompi::createTokenizedTransaction([
    'tokenTarjeta' => $card->token,
    'monto' => 29.99,
    'nombreCliente' => 'JUAN PEREZ',
    'emailCliente' => 'juan@example.com',
]);
```

### Tokenización

```php
$token = Wompi::tokenizeCard([
    'numeroTarjeta' => '4111111111111111',
    'cvv' => '123',
    'mesVencimiento' => 12,
    'anioVencimiento' => 2030,
    'nombreEnTarjeta' => 'JUAN PEREZ',
]);

$token->token;             // el token de la tarjeta
$token->tarjetaEnmascarada; // "411111******1111"
```

Listar y eliminar tarjetas tokenizadas:

```php
$cards = Wompi::listTokenizedCards();      // PagedResultDTO
$cards->items();
$cards->total();

Wompi::deleteTokenizedCard($tokenId);
```

### Consultar transacciones

```php
$transaction = Wompi::getTransaction($id);

$transaction->esAprobada;   // bool
$transaction->esReal;       // bool: false = transacción de prueba
$transaction->monto;
$transaction->codigoAutorizacion;

$transactions = Wompi::listTransactions(['ResultadoTransaccion' => 1]);
$stats = Wompi::getDailyTransactionStats();
$receipt = Wompi::getTransactionReceipt($id);       // contenido crudo
$response = Wompi::getTransactionReceiptResponse($id); // Response completa
```

## 🔔 Webhooks

### El flujo recomendado

Publica la ruta y el controlador, y engancha tus listeners a los eventos:

```php
use Rmirandasv\Wompi\Events\WompiPaymentProcessed;

class ProcessWompiPayment
{
    public function handle(WompiPaymentProcessed $event): void
    {
        if (! $event->isSuccessful) {
            return;
        }

        $order = Order::where('reference', $event->webhook->reference())->first();

        $order?->update([
            'status' => 'paid',
            'transaction_id' => $event->webhook->idTransaccion(),
            'paid_at' => now(),
        ]);
    }
}
```

Registra el listener en `app/Providers/EventServiceProvider.php` o con `Event::listen()`.

### La regla de oro: `EsProductiva`

El webhook incluye `EsProductiva`. Cuando es `false` **no se cobró al cliente**: fue una transacción de prueba.

```php
$event->isSuccessful;                 // true solo si fue aprobada Y fue un cobro real
$event->webhook->isApproved();         // la transacción fue aprobada
$event->webhook->isProduction();       // fue un cobro real
$event->webhook->isTestTransaction();  // fue una prueba
```

`isSuccessful` **nunca** es `true` para una transacción de prueba, así que una orden nunca se marca como pagada por un cobro que no ocurrió.

### Normalización del payload

Wompi no es consistente con las mayúsculas: el **webhook** usa PascalCase (`IdTransaccion`, `ResultadoTransaccion`, `Monto`) y las **respuestas del API** usan camelCase (`idTransaccion`, `resultadoTransaccion`, `monto`).

El objeto `WompiWebhook` normaliza ambos casos, así que no tienes que preocuparte:

```php
$webhook->idTransaccion();
$webhook->amount();
$webhook->reference();      // EnlacePago.IdentificadorEnlaceComercio
$webhook->productName();
$webhook->paymentMethod();  // "TarjetaCreditoDebito", "PagoNormal", ...
$webhook->authorizationCode();
$webhook->occurredAt();
$webhook->customer();
$webhook->idempotencyKey(); // "idTransaccion:idIntentoPago"
$webhook->raw();            // el payload original, intacto
```

### Idempotencia

Wompi reintenta la entrega de un webhook si no recibe un 2xx, así que **el mismo pago puede llegar más de una vez**. Usa `idempotencyKey()` para hacer tu handler idempotente:

```php
if (Cache::has("wompi:{$event->webhook->idempotencyKey()}")) {
    return;
}
Cache::put("wompi:{$event->webhook->idempotencyKey()}", true, now()->addMinutes(10));
```

### Validar el redirect

```php
public function paymentReturn(Request $request)
{
    $params = $request->query();

    if (! Wompi::validateRedirectParams($params, (string) $request->input('hash'))) {
        abort(403, 'Firma inválida');
    }

    if (Wompi::isRedirectApproved($params)) {
        return view('payment.success', ['id' => $params['idTransaccion']]);
    }

    return view('payment.failed', ['message' => $params['mensaje']]);
}
```

`isRedirectApproved()` evalúa `esAprobada` sin distinguir mayúsculas, porque Wompi envía `"true"` o `"True"`.

> [!WARNING]
> La URL de redirect depende del navegador del pagador: puede no llegar. Confirma siempre el pago con el webhook **y** el redirect, como recomienda Wompi.

## 🧪 Modo prueba

Wompi **no tiene un endpoint de transacción de prueba**. El modo de prueba se activa putting tu negocio en modo desarrollo en el [panel de Wompi](https://panel.wompi.sv/), y para simular un cobro rechazado se envía `111` en el CVV.

```php
$aplicativo = Wompi::getAplicativoData();
$aplicativo->estaProductivo;   // false = el negocio está en modo prueba
```

## 🛡️ Seguridad

### PHP2Shell

El paquete requiere **PHP 8.2.35 o superior** porque las versiones anteriores son vulnerables a **CVE-2026-109618** y **CVE-2026-109619** (colectivamente *PHP2Shell*): dos RCE sin autenticación con CVSS 9.2, divulgadas el 21 de octubre de 2026 y ya en el [catálogo KEV de CISA](https://www.cisa.gov/news-events/alerts/2026/10/21/critical-vulnerabilities-php-require-immediate-patching).

| Rama | Versión mínima segura |
|---|---|
| 8.2 | 8.2.35 |
| 8.3 | 8.3.36 |
| 8.4 | 8.4.27 |
| 8.5 | 8.5.12 |

### Datos de tarjeta

- El PAN y el CVV **nunca** se escriben en los logs. El enmascarado es recursivo, así que también cubre `tarjetaCreditoDebido.numeroTarjeta`, y cualquier valor con forma de número de tarjeta se enmascara aunque Wompi lo renombre.
- Los payloads no se registran a menos que actives `WOMPI_LOG_PAYLOADS=true`, y aun así se enmascaran.
- `getAplicativoData()` y `getAplicativoById()` **redactan** el `clientSecret` que el API devuelve.

### Firma de los webhooks

La firma HMAC se compara con `hash_equals()`, y una firma inválida se rechaza con **403** (nunca 200), para que los intentos de falsificación queden registrados.

## 📚 Métodos disponibles

| Método | Descripción |
|--------|-------------|
| `createPaymentLink()` | Crea un enlace de pago |
| `listPaymentLinks()` | Lista los enlaces de pago |
| `getPaymentLink()` | Obtiene un enlace de pago |
| `updatePaymentLink()` | Actualiza un enlace de pago |
| `activatePaymentLink()` | Reactiva un enlace de pago |
| `deactivatePaymentLink()` | Desactiva un enlace de pago |
| `addPaymentLinkImage()` | Sube una imagen del botón de pago |
| `deletePaymentLinkImage()` | Elimina una imagen del botón de pago |
| `createTransaction3DS()` | Crea una transacción con 3DS |
| `createTokenizedTransaction()` | Cobra una tarjeta tokenizada sin 3DS |
| `getTransaction()` | Consulta una transacción |
| `listTransactions()` | Lista las transacciones |
| `getTransactionReceipt()` | Descarga el comprobante |
| `getTransactionReceiptResponse()` | Descarga el comprobante como `Response` |
| `getDailyTransactionStats()` | Estadísticas diarias |
| `tokenizeCard()` | Tokeniza una tarjeta |
| `listTokenizedCards()` | Lista las tarjetas tokenizadas |
| `deleteTokenizedCard()` | Elimina una tarjeta tokenizada |
| `createRecurringPaymentLink()` | Crea un enlace de pago recurrente (suscripción) |
| `listRecurringPaymentLinks()` | Lista los enlaces recurrentes |
| `getRecurringPaymentLink()` | Obtiene un enlace recurrente |
| `updateRecurringPaymentLink()` | Actualiza un enlace recurrente |
| `deactivateRecurringPaymentLink()` | Desactiva un enlace recurrente |
| `listRecurringSubscriptions()` | Lista los suscriptores de un enlace recurrente |
| `getAplicativoData()` | Datos y capacidades del aplicativo |
| `getAplicativoById()` | Datos de un aplicativo por id |
| `getAccount()` | Datos de la cuenta |
| `getRegions()` | Países y regiones aceptados |
| `handleWebhook()` | Valida, normaliza y despacha los eventos del webhook |
| `validateWebhookSignature()` | Valida la firma HMAC |
| `validateRedirectParams()` | Valida los parámetros del redirect |
| `isSuccessfulPayment()` | ¿El webhook describe un pago exitoso? |
| `isRedirectApproved()` | ¿El redirect describe una transacción aprobada? |

## 🛡️ Manejo de excepciones

```php
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;
use Rmirandasv\Wompi\Exceptions\WompiApiException;
use Rmirandasv\Wompi\Exceptions\WompiValidationException;

try {
    $response = Wompi::createPaymentLink($data);
} catch (WompiValidationException $e) {
    // Los datos del DTO no son válidos
    Log::error($e->getMessage(), $e->getErrors());
} catch (WompiApiException $e) {
    // El API de Wompi respondió con un error
    Log::error($e->getMessage(), $e->getResponseBody());

    $e->getStatusCode();     // 400, 401, 403...
    $e->getMessages();       // el array "mensajes" de Wompi
    $e->getServiceError();   // "servicioError"
    $e->getErrorType();      // "subTipoError"
} catch (PaymentGatewayException $e) {
    // Fallo de red o de autenticación
    Log::error($e->getMessage());
}
```

## 🔧 Inyección de dependencias

```php
use Rmirandasv\Wompi\Contracts\WompiClientInterface;

class PaymentService
{
    public function __construct(private WompiClientInterface $wompi) {}
}
```

## 🧪 Testing

El paquete viene testeado con [PEST PHP](https://pestphp.com). Consulta [TESTING.md](TESTING.md) para la guía completa.

```bash
composer test
composer test:coverage
```

## 📖 Documentación oficial

- [Documentación Wompi](https://docs.wompi.sv/)
- [Swagger](https://api.wompi.sv/index.html)
- [Crear Enlace de Pago](https://docs.wompi.sv/metodos-api/enlace-de-pago)
- [Crear Enlace Recurrente](https://docs.wompi.sv/metodos-api/crear-enlace-pago-recurrentes)
- [Transacciones 3DS](https://docs.wompi.sv/metodos-api/crear-transaccion-compra-3ds)
- [Tokenización](https://docs.wompi.sv/metodos-api/tokenizacion)
- [Webhooks](https://docs.wompi.sv/webhook/definicion-webhook)

## 🤖 Integración con IA (Prompt-to-Code)

El paquete incluye una **Skill/Regla de IA** en [`laravel-wompi-skill/SKILL.md`](laravel-wompi-skill/SKILL.md) para integrar pagos en minutos con Cursor, Windsurf, Claude Code o Gemini CLI.

```bash
# Cursor / Windsurf
cp laravel-wompi-skill/SKILL.md .cursor/rules/wompi.md
```

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Fork del proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit de tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este paquete es software de código abierto licenciado bajo la [Licencia MIT](LICENSE).

---

**⚠️ Nota de Seguridad**: Nunca compartas tus credenciales de Wompi. Mantenlas en variables de entorno y nunca las commitees a control de versiones.
