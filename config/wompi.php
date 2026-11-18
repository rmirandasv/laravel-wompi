<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Wompi API Base URLs
    |--------------------------------------------------------------------------
    |
    | "api_url" is the base URL of the Wompi API. It does NOT include a "/v1"
    | suffix: the "/v1" you see in the Swagger UI is the version of the OpenAPI
    | document (https://api.wompi.sv/swagger/v1/swagger.json), not a prefix of
    | the API itself. Requests to https://api.wompi.sv/v1/... return HTTP 404.
    |
    | "auth_url" is the OAuth2 client_credentials token endpoint.
    |
    */
    'api_url' => env('WOMPI_API_URL', 'https://api.wompi.sv'),
    'auth_url' => env('WOMPI_AUTH_URL', 'https://id.wompi.sv/connect/token'),
    'audience' => env('WOMPI_AUDIENCE', 'wompi_api'),

    /*
    |--------------------------------------------------------------------------
    | Wompi API Credentials
    |--------------------------------------------------------------------------
    |
    | These are the credentials for the Wompi API. You can get them from
    | your Wompi dashboard (the "App ID" is the client id and the
    | "API Secret" is the client secret).
    |
    | The client secret is also the HMAC key used to validate incoming
    | webhooks and redirect URL parameters. Wompi issues a single secret
    | per application, so there is no separate "webhook secret".
    |
    */
    'client_id' => env('WOMPI_CLIENT_ID'),
    'client_secret' => env('WOMPI_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Options
    |--------------------------------------------------------------------------
    |
    | Timeouts (in seconds) applied to every outgoing request, and how many
    | times an idempotent request (GET / DELETE) is retried when the
    | network fails. POST requests are never retried automatically to avoid
    | charging a customer twice.
    |
    */
    'timeout' => (int) env('WOMPI_TIMEOUT', 30),
    'connect_timeout' => (int) env('WOMPI_CONNECT_TIMEOUT', 10),
    'retry' => (int) env('WOMPI_RETRY', 2),

    /*
    |--------------------------------------------------------------------------
    | Access Token Cache
    |--------------------------------------------------------------------------
    |
    | The OAuth2 access token is cached so that we do not request a new one on
    | every call. The cache key is namespaced by client id, so two different
    | Wompi applications never share a token.
    |
    | "ttl_margin" is subtracted from the token lifetime so that a token is
    | refreshed shortly before it actually expires.
    |
    */
    'token_cache' => [
        'store' => env('WOMPI_TOKEN_CACHE_STORE'),
        'prefix' => env('WOMPI_TOKEN_CACHE_PREFIX', 'wompi:access_token'),
        'ttl_margin' => (int) env('WOMPI_TOKEN_TTL_MARGIN', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | By default the package only logs a one-line debug entry per request and
    | never writes request or response payloads. Enable "log_payloads" to also
    | log payloads: card numbers, CVVs and other card data are always masked
    | before they reach the log.
    |
    */
    'log_payloads' => (bool) env('WOMPI_LOG_PAYLOADS', false),

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | Path and route name used by the publishable webhook route
    | (php artisan vendor:publish --tag=wompi-webhook).
    |
    */
    'webhook' => [
        'path' => env('WOMPI_WEBHOOK_PATH', 'webhooks/wompi'),
        'route' => env('WOMPI_WEBHOOK_ROUTE_NAME', 'wompi.webhook'),
        'rate_limit' => (int) env('WOMPI_WEBHOOK_RATE_LIMIT', 120),
    ],
];
