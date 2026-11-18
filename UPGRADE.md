# Actualizar de v1 a v2

La v2.0 es un **cambio incompatible**. No hay aliases deprecados: los métodos que cambiaron de nombre o de comportamiento se eliminaron.

## ⚠️ Por qué v2

Durante el análisis de la v1 se verificaron **en vivo** (contra `api.wompi.sv` e `id.wompi.sv`) los endpoints que el paquete invocaba. El resultado:

| Método de v1 | Ruta que invocaba | Respuesta real | Ruta correcta |
|---|---|---|---|
| `createPaymentLink()` | `POST {api_url}/EnlacePago` | **404** (por el `/v1`) | `POST /EnlacePago` |
| `createTransaction3DS()` | `POST {api_url}/Transaccion` | **404** | `POST /TransaccionCompra/3Ds` |
| `tokenizeCard()` | `POST {api_url}/Tokenizacion` | **404** (por el `/v1`) | `POST /Tokenizacion` |
| `getTokenizedCard()` | `GET {api_url}/Tokenizacion/{id}` | **405** | `GET /Tokenizacion` (lista) |
| `deleteTokenizedCard()` | `DELETE {api_url}/Tokenizacion/{id}` | **404** (por el `/v1`) | `DELETE /Tokenizacion/{tokenId}` |
| `createRecurringCharge()` | `POST {api_url}/CargoRecurrente` | **404** | `POST /EnlacePagoRecurrente` |
| `executeTestTransaction()` | `POST {api_url}/TransaccionPrueba` | **404** | No existe |
| `getAplicativoData()` | `GET {api_url}/Aplicativo` | **404** (por el `/v1`) | `GET /Aplicativo` |

Además, las dos URLs por defecto de `config/wompi.php` eran incorrectas:

- `WOMPI_API_URL=https://api.wompi.sv/v1` → **404 en todas las rutas**. La base correcta es `https://api.wompi.sv` (el `/v1` es la versión del *spec* OpenAPI, no del API).
- `WOMPI_AUTH_URL=https://id.wompi.sv` → devuelve la página HTML "Admin Seguridad". El endpoint OAuth2 correcto es `https://id.wompi.sv/connect/token`.

**Consecuencia:** con la configuración por defecto de v1, *ninguna* llamada al API de Wompi podía funcionar. Solo funcionaban la validación de firma del webhook y la del redirect, que no hacen HTTP.

> El reporte de "las suscripciones no funcionan" **no era un problema de Wompi**: `createRecurringCharge()` llamaba a `/CargoRecurrente`, una ruta que no existe. La ruta correcta es `/EnlacePagoRecurrente`.

## Requisitos

- **PHP 8.2.35** o superior (ver [PHP2Shell](#php2shell))
- Laravel 10, 11, 12 o 13

```bash
composer require rmirandasv/laravel-wompi:^2.0
```

## Tabla de equivalencias

### Configuración

| v1 | v2 |
|---|---|
| `WOMPI_API_URL=https://api.wompi.sv/v1` | `WOMPI_API_URL=https://api.wompi.sv` |
| `WOMPI_AUTH_URL=https://id.wompi.sv` | `WOMPI_AUTH_URL=https://id.wompi.sv/connect/token` |
| `WOMPI_WEBHOOK_SECRET` | **Eliminado.** Usa `WOMPI_CLIENT_SECRET` |

### Métodos

| v1 | v2 |
|---|---|
| `createRecurringCharge(array $data)` | `createRecurringPaymentLink(array $data)` (suscripción) o `createTokenizedTransaction(array $data)` (cargo a tarjeta tokenizada) |
| `executeTestTransaction(array $data)` | **Eliminado.** El endpoint no existe. Activa el modo desarrollo en el panel de Wompi |
| `getTokenizedCard(string $tokenId)` | `listTokenizedCards()` — Wompi no tiene un endpoint para leer una tarjeta tokenizada por id |
| `getAplicativoData(): array` | `getAplicativoData(): AplicativoResponseDTO` |
| `validateWebhookRequest(Request $request): array` | `handleWebhook(Request $request): WompiWebhook` |

### DTOs de request

| v1 | v2 |
|---|---|
| `TokenizeCardRequestDTO::$mesExpiracion` | `$mesVencimiento` (int) |
| `TokenizeCardRequestDTO::$anioExpiracion` | `$anioVencimiento` (int) |
| `Transaction3DSRequestDTO::$numeroTarjeta` (raíz) | `$tarjetaCreditoDebido` (objeto anidado) |
| `Transaction3DSRequestDTO::$mesExpiracion` | `$tarjetaCreditoDebido['mesVencimiento']` (int) |
| `Transaction3DSRequestDTO::$anioExpiracion` | `$tarjetaCreditoDebido['anioVencimiento']` (int) |

Los DTOs de 3DS ahora **exigen** los datos del cliente que Wompi marca como requeridos: `urlRedirect`, `nombre`, `apellido`, `email`, `ciudad`, `direccion`, `idPais`, `idRegion`, `codigoPostal` y `telefono`.

### DTOs de response

| v1 (no existía) | v2 |
|---|---|
| — | `AplicativoResponseDTO`, `TransactionDetailDTO`, `RecurringPaymentLinkResponseDTO`, `PagedResultDTO`, `DailyTransactionStatsDTO` |

Los DTOs ahora implementan `Arrayable` y `JsonSerializable`, así que `response()->json($dto)` devuelve el payload y no `{}`.

`TokenizedCardResponseDTO` documenta los campos reales de Wompi: `token` y `tarjetaEnmascarada` (al tokenizar), `tokenId` y `maskedCreditCardNumber` (al listar).

### Eventos

| v1 | v2 |
|---|---|
| `WompiWebhookReceived($payload)` | `WompiWebhookReceived($payload, WompiWebhook $webhook)` |
| `WompiPaymentProcessed($payload, $isSuccessful)` | `WompiPaymentProcessed($payload, $isSuccessful, WompiWebhook $webhook)` |
| `use Dispatchable, SerializesModels` | Eliminados (no hacían falta y arrastraban `illuminate/foundation` e `illuminate/queue`) |

### Comportamiento del webhook

En v1, `isSuccessfulPayment()` buscaba `resultadoTransaccion` en camelCase, pero el webhook de Wompi lo envía en **PascalCase** (`ResultadoTransaccion`). La función **siempre devolvía `false`**.

En v2 el payload se normaliza, así que ambos casos funcionan. Además:

- `EsProductiva` ahora se respeta: una transacción de prueba **nunca** marca una orden como pagada. En v1 sí lo hacía.
- `validateWebhookRequest()` devuelve un `WompiWebhook` normalizado en vez del array crudo.
- El middleware `wompi.webhook` y `handleWebhook()` despachan **exactamente un** `WompiWebhookReceived` y un `WompiPaymentProcessed`, sin importar cuál uses.

### Comportamiento del redirect

En v1, un redirect al que le faltaba un parámetro se validaba contra un hash calculado sobre una cadena más corta. En v2, si falta cualquiera de los parámetros esperados, la validación **falla**.

Además, `isRedirectApproved()` evalúa `esAprobada` sin distinguir mayúsculas, porque Wompi envía `"true"` o `"True"` indistintamente.

### Excepciones

| v1 | v2 |
|---|---|
| `PaymentGatewayException` con `getStatusCode()` / `getResponseBody()` | Se mantiene |
| — | `WompiApiException` con `getMessages()`, `getServiceError()`, `getErrorType()` |
| — | `WompiAuthenticationException` |

`WompiApiException` y `WompiAuthenticationException` extienden `PaymentGatewayException`, así que tu `catch` existente sigue funcionando.

## PHP2Shell

La v1 exigía `php: ^8.2`, lo que permite instalar 8.2.0–8.2.34. Esas versiones son vulnerables a **CVE-2026-109618** y **CVE-2026-109619** (*PHP2Shell*): dos RCE sin autenticación con CVSS 9.2, divulgadas el 21 de octubre de 2026 y ya en el catálogo KEV de CISA.

La v2 exige una versión parcheada:

```json
"php": "^8.2.35 || ^8.3.36 || ^8.4.27 || ^8.5.12"
```

Si tu servidor corre una versión anterior, actualiza PHP antes de instalar la v2. Si no puedes actualizar de inmediato, usa [Mitigaciones de la NCSC](https://www.ncsc.govt.nz/) o lleva la aplicación fuera de línea.

## Checklist de migración

- [ ] Actualiza PHP a una versión parcheada.
- [ ] `composer require rmirandasv/laravel-wompi:^2.0`
- [ ] Corrige `WOMPI_API_URL` (sin `/v1`) y `WOMPI_AUTH_URL` (con `/connect/token`).
- [ ] Elimina `WOMPI_WEBHOOK_SECRET` de tu `.env`.
- [ ] Renombra `createRecurringCharge()` según lo que realmente hacías.
- [ ] Elimina las llamadas a `executeTestTransaction()` y `getTokenizedCard()`.
- [ ] Actualiza los DTOs de tokenización y 3DS a los nombres de campo nuevos.
- [ ] Revisa tus listeners: `isSuccessful` ahora es `false` para las transacciones de prueba.
- [ ] Publica la ruta del webhook: `php artisan vendor:publish --tag="wompi-webhook"`.
- [ ] Haz tu handler de webhook idempotente con `idempotencyKey()`.
- [ ] Ejecuta `composer test` y revisa que tus tests siguen en verde.
