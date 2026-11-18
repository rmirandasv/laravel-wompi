---
name: laravel-wompi-integration
description: >-
  Integrates Wompi El Salvador (payment links, 3DS, tokenization, subscriptions,
  webhooks) in Laravel using rmirandasv/laravel-wompi. Use this skill when the
  user wants to implement checkout flows, validate payment redirects, handle
  webhooks, or sell subscriptions through Wompi.
---

# 🚀 Wompi Laravel Integration Skill

This skill provides expert guidance for integrating the **Wompi El Salvador** payment gateway into Laravel applications using the `rmirandasv/laravel-wompi` package.

## 🤖 AI Agent Instructions

When this skill is active, you MUST:
1.  **Use version 2 or newer**: `rmirandasv/laravel-wompi >= 2.0.0`. Version 1 called endpoints that do not exist in the Wompi API and used two wrong default URLs, so no request could succeed.
2.  **Follow the DTO patterns**: Use the provided Request/Response DTOs for type safety and local validation.
3.  **Security first**: Always validate the redirect signature, always use the `wompi.webhook` middleware, and never log card data.
4.  **Idempotency**: Wompi retries webhook deliveries. Make the webhook handler idempotent.
5.  **Respect `EsProductiva`**: A test transaction was never billed to the customer and must never mark an order as paid.
6.  **UX consistency**: Implement polling or interactive status checks for the user returning from a redirect, since webhooks may be delayed.

## ⚙️ Configuration (v2)

```env
WOMPI_API_URL=https://api.wompi.sv
WOMPI_AUTH_URL=https://id.wompi.sv/connect/token
WOMPI_CLIENT_ID=...
WOMPI_CLIENT_SECRET=...
```

- `WOMPI_API_URL` must **not** contain `/v1`; the `/v1` in the Swagger URL is the OpenAPI document version, not an API prefix. With `/v1` every request returns 404.
- `WOMPI_AUTH_URL` must point to the OAuth2 `client_credentials` endpoint (`/connect/token`).
- There is **no** `WOMPI_WEBHOOK_SECRET`: the HMAC key is the `WOMPI_CLIENT_SECRET`.
- The package requires PHP **8.2.35+** (PHP2Shell: CVE-2026-109618 / CVE-2026-109619).

## 🛠 Integration Workflows

### 1. Payment Link (Checkout)

- **Method**: `Wompi::createPaymentLink($data)` or `Wompi::createPaymentLink(PaymentLinkRequestDTO::fromArray($data))`
- **Required fields**: `identificadorEnlaceComercio` (use a unique, searchable order id), `nombreProducto`, and `monto` (>= 0.01).
- **Redirect**: For Inertia.js use `Inertia::location($url)`, for Blade use `redirect()->away($url)`.

```php
$response = Wompi::createPaymentLink([
    'identificadorEnlaceComercio' => 'ORDER-'.$order->uuid,
    'nombreProducto' => 'Mi producto',
    'monto' => 25.50,
    'configuracion' => [
        'urlRedirect' => route('payment.return'),
        'urlWebhook' => route('wompi.webhook'),
    ],
]);
```

### 2. Redirect Validation (Mandatory)

Wompi sends the user back to your `urlRedirect` with query parameters and a `hash`.

- **Validation**: `Wompi::validateRedirectParams($request->query(), (string) $request->input('hash'))`.
- **Approval**: use `Wompi::isRedirectApproved($params)` — it is case-insensitive, because Wompi sends `"true"` or `"True"`.
- **Note**: This validation uses your `WOMPI_CLIENT_SECRET`.

### 3. Webhook Handling

- **Publish the route and controller**: `php artisan vendor:publish --tag=wompi-webhook"`.
- **Register it** in `bootstrap/app.php` (Laravel 11+) inside the `api` middleware group, which does not include CSRF:
  ```php
  ->withRouting(then: function () {
      Route::middleware('api')->group(base_path('routes/wompi.php'));
  })
  ```
- **Events**: Listen for `WompiPaymentProcessed` or `WompiWebhookReceived`.
- **Casing**: Wompi sends the webhook in PascalCase and the API responses in camelCase. The `WompiWebhook` object normalizes both, so use `$event->webhook->reference()` and `$event->webhook->idTransaccion()` instead of reading raw array keys.

```php
Event::listen(WompiPaymentProcessed::class, function ($event) {
    if (! $event->isSuccessful) {
        return;   // rejected, or a test transaction that was never charged
    }

    $order = Order::where('reference', $event->webhook->reference())->first();
    $order?->update(['status' => 'paid']);
});
```

### 4. Subscriptions

Two different Wompi features — do not mix them up:

- **Recurring payment link** (the customer subscribes themselves): `Wompi::createRecurringPaymentLink(['diaDePago' => 5, 'nombre' => 'Plan mensual', 'monto' => 29.99])`. Share the returned `urlEnlace`. List subscribers with `Wompi::listRecurringSubscriptions($id)`. Wompi may require the merchant to enable this service.
- **Tokenized charge** (you charge a stored card): `Wompi::createTokenizedTransaction(['tokenTarjeta' => $token, 'monto' => 29.99, 'nombreCliente' => ..., 'emailCliente' => ...])`.

### 5. Tokenization

```php
$token = Wompi::tokenizeCard([
    'numeroTarjeta' => '4111111111111111',
    'cvv' => '123',
    'mesVencimiento' => 12,      // NOT mesExpiracion
    'anioVencimiento' => 2030,    // NOT anioExpiracion
]);
$token->token;                  // the token to store
$token->tarjetaEnmascarada;     // "411111******1111"
```

## ⚠️ Common Mistakes

| Mistake | Consequence |
|---|---|
| `WOMPI_API_URL=https://api.wompi.sv/v1` | Every request returns 404 |
| `WOMPI_AUTH_URL=https://id.wompi.sv` | Returns an HTML login page, no token |
| Using `mesExpiracion` / `anioExpiracion` | The API rejects the payload |
| Putting the card at the root of the 3DS payload | The API rejects the payload; it must be nested in `tarjetaCreditoDebido` |
| Reading `$payload['resultadoTransaccion']` | The webhook uses PascalCase, so it is always null |
| Ignoring `EsProductiva` | A test transaction marks the order as paid |
| Comparing `$params['esAprobada'] === 'true'` | Wompi also sends `'True'`; use `isRedirectApproved()` |
| Calling `executeTestTransaction()` | The endpoint does not exist; enable the development mode in the Wompi panel |

## 📋 Prompting Examples for Users

- *"Integrate Wompi checkout: create a payment link for my 'Order' model, handle the return URL with hash validation, and set up a webhook listener using events."*
- *"Add subscription support with Wompi: create a recurring payment link and list the subscribers."*
- *"Charge a tokenized card monthly with Wompi."*
- *"Fix my Wompi redirect validation: it always returns false when coming back from a payment link."*

## 📚 References

- [Official Documentation](https://docs.wompi.sv/)
- [Wompi Swagger](https://api.wompi.sv/index.html)
- [Package Repository](https://github.com/rmirandasv/laravel-wompi)
- [Upgrade guide (v1 → v2)](https://github.com/rmirandasv/laravel-wompi/blob/main/UPGRADE.md)

## 🔧 AI Tool Compatibility

- **Cursor/Windsurf**: Place this file in `.cursor/rules/wompi.md` or `.windsurf/rules/wompi.md`.
- **Gemini CLI**: Activate via `activate_skill('laravel-wompi-integration')` or include the file as context.
