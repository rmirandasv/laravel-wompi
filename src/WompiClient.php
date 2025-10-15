<?php

namespace Rmirandasv\Wompi;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rmirandasv\Wompi\Exceptions\ConfigurationException;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;

class WompiClient
{
    public function __construct(
        private ?string $authUrl,
        private ?string $apiUrl,
        private ?string $clientId,
        private ?string $clientSecret,
    ) {
        if (! $this->authUrl || ! $this->clientId || ! $this->clientSecret || ! $this->apiUrl) {
            throw new ConfigurationException('Wompi credentials are not set');
        }
    }

    /**
     * Get OAuth2 access token (cached for ~59 minutes)
     */
    private function getAccessToken(): string
    {
        $accessToken = Cache::get('wompi_access_token');

        if (! $accessToken) {
            [$accessToken, $expiresIn] = $this->getAccessTokenFromWompi();
            Cache::put('wompi_access_token', $accessToken, $expiresIn);
        }

        return $accessToken;
    }

    /**
     * Fetch a fresh access token from Wompi
     */
    private function getAccessTokenFromWompi(): array
    {
        try {
            $response = Http::asForm()->post($this->authUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'audience' => 'wompi_api',
            ])->throw();

            $data = $response->json();

            $accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'];

            return [$accessToken, $expiresIn];
        } catch (RequestException $e) {
            Log::error('Error getting Wompi access token', [
                'error' => $e->getMessage(),
            ]);
            throw new PaymentGatewayException('Failed to authenticate with Wompi', 0, $e);
        }
    }

    /**
     * Make authenticated API request
     */
    private function makeAuthenticatedRequest(string $method, string $endpoint, array $data = []): array
    {
        try {
            $accessToken = $this->getAccessToken();
            $url = sprintf('%s/%s', rtrim($this->apiUrl, '/'), ltrim($endpoint, '/'));
		Log::info($url);

            $response = Http::withToken($accessToken)
                ->$method($url, $data)
                ->throw();

            return $response->json() ?? [];
        } catch (RequestException $e) {
            Log::error("Wompi API request failed: {$method} {$endpoint}", [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw new PaymentGatewayException("API request failed: {$endpoint}", 0, $e);
        }
    }

    /**
     * Create a payment link (Enlace de Pago)
     * 
     * @see https://docs.wompi.sv/metodos-api/enlace-de-pago
     */
    public function createPaymentLink(array $data): array
    {
        return $this->makeAuthenticatedRequest('post', 'EnlacePago', $data);
    }

    /**
     * Create a 3DS purchase transaction (Transacción Compra con 3DS)
     * 
     * @see https://docs.wompi.sv/metodos-api/crear-transaccion-compra-3ds
     */
    public function createTransaction3DS(array $data): array
    {
        return $this->makeAuthenticatedRequest('post', 'Transaccion', $data);
    }

    /**
     * Get aplicativo data (configuration and capabilities)
     * 
     * @see https://docs.wompi.sv/metodos-api/datos-aplicativo
     */
    public function getAplicativoData(): array
    {
        return $this->makeAuthenticatedRequest('get', 'Aplicativo');
    }

    /**
     * Tokenize a card for future use
     * 
     * @see https://docs.wompi.sv/metodos-api/tokenizacion
     */
    public function tokenizeCard(array $data): array
    {
        return $this->makeAuthenticatedRequest('post', 'Tokenizacion', $data);
    }

    /**
     * Get a tokenized card by ID
     */
    public function getTokenizedCard(string $tokenId): array
    {
        return $this->makeAuthenticatedRequest('get', "Tokenizacion/{$tokenId}");
    }

    /**
     * Delete a tokenized card
     */
    public function deleteTokenizedCard(string $tokenId): array
    {
        return $this->makeAuthenticatedRequest('delete', "Tokenizacion/{$tokenId}");
    }

    /**
     * Create a recurring charge using a tokenized card
     * 
     * @see https://docs.wompi.sv/metodos-api/cargos-recurrentes
     */
    public function createRecurringCharge(array $data): array
    {
        return $this->makeAuthenticatedRequest('post', 'CargoRecurrente', $data);
    }

    /**
     * Execute test transactions (for development/testing)
     */
    public function executeTestTransaction(array $data): array
    {
        return $this->makeAuthenticatedRequest('post', 'TransaccionPrueba', $data);
    }

    /**
     * Validate webhook HMAC signature
     * 
     * @see https://docs.wompi.sv/webhook/validar-webhook
     */
    public function validateWebhookSignature(string $body, string $receivedHash): bool
    {
        if (! $this->clientSecret) {
            throw new ConfigurationException('Wompi webhook secret is not set');
        }

        $calculatedHash = hash_hmac('sha256', $body, $this->clientSecret);

        return hash_equals($calculatedHash, $receivedHash);
    }

    /**
     * Validate and parse webhook request
     * Returns the parsed webhook data as an array
     */
    public function validateWebhookRequest(Request $request): array
    {
        $wompiHash = $request->header('wompi_hash');

        if (! $wompiHash) {
            throw new PaymentGatewayException('Webhook request invalid: wompi_hash header is required');
        }

        $body = $request->getContent();

        if (empty($body)) {
            throw new PaymentGatewayException('Webhook request invalid: body is required');
        }

        if (! $this->validateWebhookSignature($body, $wompiHash)) {
            throw new PaymentGatewayException('Webhook request invalid: signature is invalid');
        }

        $webhookData = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new PaymentGatewayException('Webhook request invalid: json is invalid');
        }

        return $webhookData;
    }

    /**
     * Validate redirect URL parameters
     * 
     * @see https://docs.wompi.sv/redirect-url/validar-parametros-url-redirect
     */
    public function validateRedirectParams(array $params, string $receivedHash): bool
    {
        if (! $this->clientSecret) {
            throw new ConfigurationException('Wompi webhook secret is not set');
        }

        $concatenated = ($params['idTransaccion'] ?? '') .
                       ($params['monto'] ?? '') .
                       ($params['esReal'] ?? '') .
                       ($params['formaPago'] ?? '') .
                       ($params['esAprobada'] ?? '') .
                       ($params['codigoAutorizacion'] ?? '') .
                       ($params['mensaje'] ?? '');

        $calculatedHash = hash_hmac('sha256', $concatenated, $this->clientSecret);

        return hash_equals($calculatedHash, $receivedHash);
    }

    /**
     * Check if a webhook indicates a successful payment
     */
    public function isSuccessfulPayment(array $webhookData): bool
    {
        return isset($webhookData['resultadoTransaccion']) && 
               $webhookData['resultadoTransaccion'] === 'ExitosaAprobada';
    }
}
