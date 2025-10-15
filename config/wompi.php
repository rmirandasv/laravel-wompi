<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Wompi API Credentials
    |--------------------------------------------------------------------------
    |
    | These are the credentials for the Wompi API. You can get them from
    | your Wompi dashboard. You should set them in your .env file.
    |
    | Auth URL: OAuth2 authentication endpoint
    | API URL: Base URL for Wompi API requests
    | Client ID: Your Wompi application client ID
    | Client Secret: Your Wompi application client secret
    |
    */
    'auth_url' => env('WOMPI_AUTH_URL', 'https://id.wompi.sv'),
    'api_url' => env('WOMPI_API_URL', 'https://api.wompi.sv/v1'),
    'client_id' => env('WOMPI_CLIENT_ID'),
    'client_secret' => env('WOMPI_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | This secret is used to verify the HMAC signature of incoming webhooks
    | and redirect URL parameters. You should set this in your Wompi dashboard
    | and in your .env file.
    |
    | Important: Keep this secret safe and never commit it to version control.
    |
    */
    'webhook_secret' => env('WOMPI_WEBHOOK_SECRET'),
];
