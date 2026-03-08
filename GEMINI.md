# GEMINI.md - Laravel Wompi

## Project Overview

A complete and robust Laravel package for integrating the **Wompi** payment gateway (specifically for El Salvador) into Laravel applications.

### Main Technologies
- **PHP**: ^8.2 (Supports 8.2, 8.3, 8.4, 8.5)
- **Laravel**: 10.x, 11.x, 12.x
- **Testing**: [Pest PHP](https://pestphp.com)
- **HTTP Client**: Laravel's `Http` facade (Guzzle wrapper)

### Architecture

- **Facade**: `Rmirandasv\Wompi\Facades\Wompi`
- **Service Provider**: `Rmirandasv\Wompi\WompiServiceProvider`
- **Core Client**: `Rmirandasv\Wompi\WompiClient` (handles API requests and OAuth2 token management with caching)
- **Exceptions**: `ConfigurationException` and `PaymentGatewayException` in `Rmirandasv\Wompi\Exceptions`

## Building and Running

### Installation

```bash
composer install
```

### Testing

The project uses Pest PHP for testing.

```bash
# Run all tests
composer test

# Run specific test suites
composer test:unit     # Unit tests only
composer test:feature  # Feature tests only

# Other test options
composer test:coverage # Coverage report
composer test:profile  # Performance profile
```

### Configuration

Variables required in `.env`:

- `WOMPI_AUTH_URL` (Default: https://id.wompi.sv)
- `WOMPI_API_URL` (Default: https://api.wompi.sv/v1)
- `WOMPI_CLIENT_ID`
- `WOMPI_CLIENT_SECRET`
- `WOMPI_WEBHOOK_SECRET`

## Development Conventions

### Coding Style

- Follows Laravel and PSR-12 coding standards.
- Uses strict typing where possible.
- Logic is encapsulated in `WompiClient`, and exposed via the `Wompi` Facade for a clean Laravel-like API.

### Testing Practices

- **Pest PHP** is the primary testing framework.
- Tests are split into `tests/Unit` (mocked HTTP) and `tests/Feature`.
- `Orchestra Testbench` is used to provide the Laravel environment for tests.
- **Mocking**: Always use Laravel's `Http::fake()` when writing tests that interact with the Wompi API.

### Key API Methods

- `createPaymentLink(array $data)`: Generates payment URLs/QR codes.
- `createTransaction3DS(array $data)`: Proceses 3D Secure transactions.
- `tokenizeCard(array $data)`: Stores cards securely for future use.
- `createRecurringCharge(array $data)`: Processes payments with tokenized cards.
- `validateWebhookRequest(Request $request)`: Validates HMAC signatures of incoming notifications.
- `validateRedirectParams(array $params, string $hash)`: Validates return URL parameters.

## Key Files

- `src/WompiClient.php`: Core API implementation.
- `src/WompiServiceProvider.php`: Laravel integration and singleton binding.
- `config/wompi.php`: Default configuration file.
- `tests/TestCase.php`: Base test case with Orchestra Testbench setup.
- `tests/Pest.php`: Pest configuration.
