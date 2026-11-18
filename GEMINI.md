# GEMINI.md - Laravel Wompi

## Project Overview

A Laravel package for integrating the **Wompi** payment gateway (El Salvador) into Laravel applications.

### Main Technologies

- **PHP**: `^8.2.35 || ^8.3.36 || ^8.4.27 || ^8.5.12` (the floor is the patched version of each branch, required by PHP2Shell: CVE-2026-109618 / CVE-2026-109619)
- **Laravel**: 10.x, 11.x, 12.x, 13.x
- **Testing**: [Pest PHP](https://pestphp.com)
- **HTTP Client**: Laravel's `Http` facade

### Architecture

- **Facade**: `Rmirandasv\Wompi\Facades\Wompi`
- **Service Provider**: `Rmirandasv\Wompi\WompiServiceProvider`
- **Core Client**: `Rmirandasv\Wompi\WompiClient` (implements `WompiClientInterface`)
- **Transport**: `Rmirandasv\Wompi\Http\WompiTransport` (HTTP, retries, error mapping)
- **Authentication**: `Rmirandasv\Wompi\Http\WompiAuthenticator` (OAuth2 token with cache, lock and TTL margin)
- **Webhooks**: `Rmirandasv\Wompi\Webhooks\WompiWebhookHandler` + `WompiWebhook` (normalized value object)
- **Support**: `SensitiveDataMasker` (card data masking), `PayloadNormalizer` (PascalCase / camelCase normalization)
- **DTOs**: `src/DTOs/Requests` (validated input) and `src/DTOs/Responses` (typed output)
- **Exceptions**: `ConfigurationException`, `PaymentGatewayException`, `WompiApiException`, `WompiAuthenticationException`, `WompiValidationException`

## Building and Running

### Installation

```bash
composer install
```

### Testing

```bash
composer test           # all tests
composer test:unit      # unit tests only
composer test:feature   # feature tests only
composer test:coverage  # coverage report
composer test:profile   # performance profile
```

The suite is **offline**: `tests/TestCase.php` calls `Http::preventStrayRequests()`, so an unmatched `Http::fake()` pattern fails the test instead of performing a real call to `api.wompi.sv`.

### Configuration

```env
WOMPI_API_URL=https://api.wompi.sv          # NO /v1 suffix
WOMPI_AUTH_URL=https://id.wompi.sv/connect/token
WOMPI_CLIENT_ID=
WOMPI_CLIENT_SECRET=                        # also the webhook HMAC key
```

## Development Conventions

### Coding Style

- Follows Laravel and PSR-12 coding standards.
- Logic is encapsulated in `WompiClient` and exposed via the `Wompi` facade.

### Testing Practices

- **Pest PHP** is the primary testing framework.
- Tests are split into `tests/Unit` and `tests/Feature`.
- `Orchestra Testbench` provides the Laravel environment.
- **Mocking**: always use `Http::fake()` with a pattern that accounts for query strings (e.g. `https://api.wompi.sv/Tokenizacion*`).
- **Contract test**: `tests/Unit/WompiEndpointContractTest.php` asserts that every endpoint the client calls is documented in `tests/Fixtures/wompi-swagger.json` (a trimmed copy of the Wompi OpenAPI spec). The `wompi-spec.yml` workflow refreshes it weekly.

### Key API Methods

- `createPaymentLink()`: creates payment URLs / QR codes.
- `createTransaction3DS()`: 3D Secure transactions (card nested in `tarjetaCreditoDebido`).
- `createTokenizedTransaction()`: charges an already tokenized card.
- `tokenizeCard()`: stores cards for future use.
- `createRecurringPaymentLink()`: creates a subscription link the customer subscribes to.
- `listRecurringSubscriptions()`: lists the subscribers of a recurring link.
- `handleWebhook()`: validates, normalizes and dispatches the webhook events.
- `validateRedirectParams()`: validates the return URL parameters.

## Key Files

- `src/WompiClient.php`: the public API.
- `src/Http/WompiTransport.php`: HTTP layer, retries and error mapping.
- `src/Http/WompiAuthenticator.php`: OAuth2 token management.
- `src/Support/SensitiveDataMasker.php`: recursive card data masking.
- `src/Support/PayloadNormalizer.php`: webhook payload normalization.
- `src/Webhooks/WompiWebhook.php`: the normalized webhook value object.
- `src/WompiServiceProvider.php`: Laravel integration, middleware alias and publishing.
- `config/wompi.php`: default configuration.
- `routes/wompi.php`: publishable webhook route.
- `stubs/WompiWebhookController.stub`: publishable webhook controller.
- `tests/TestCase.php`: base test case with Testbench setup.
- `tests/Pest.php`: Pest configuration and shared helpers.
- `tests/Fixtures/wompi-swagger.json`: the Wompi OpenAPI contract fixture.
- `UPGRADE.md`: the v1 → v2 migration guide.
