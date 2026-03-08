<?php

namespace Rmirandasv\Wompi;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rmirandasv\Wompi\Contracts\WompiClientInterface;
use Rmirandasv\Wompi\DTOs\Requests\PaymentLinkRequestDTO;
use Rmirandasv\Wompi\DTOs\Requests\TokenizeCardRequestDTO;
use Rmirandasv\Wompi\DTOs\Requests\Transaction3DSRequestDTO;
use Rmirandasv\Wompi\DTOs\Responses\PaymentLinkResponseDTO;
use Rmirandasv\Wompi\DTOs\Responses\TokenizedCardResponseDTO;
use Rmirandasv\Wompi\DTOs\Responses\TransactionResponseDTO;
use Rmirandasv\Wompi\Events\WompiPaymentProcessed;
use Rmirandasv\Wompi\Events\WompiWebhookReceived;
use Rmirandasv\Wompi\Exceptions\ConfigurationException;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;

class WompiClient implements WompiClientInterface
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
            
            $startTime = microtime(true);
            Log::info("Wompi API Request: {$method} {$endpoint}", [
                'url' => $url,
                // Mask sensitive card numbers if present before logging
                'payload' => $this->maskSensitiveData($data),
            ]);

            $response = Http::withToken($accessToken)
                ->$method($url, $data)
                ->throw();

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            Log::info("Wompi API Response: {$method} {$endpoint}", [
                'status' => $response->status(),
                'duration_ms' => $duration,
            ]);

            return $response->json() ?? [];
        } catch (RequestException $e) {
            $response = $e->response;
            $statusCode = $response->status();
            $errorBody = $response->json();
            
            Log::error("Wompi API request failed: {$method} {$endpoint}", [
                'status' => $statusCode,
                'error_body' => $errorBody,
                'payload' => $this->maskSensitiveData($data),
                'headers' => $response->headers(),
            ]);
            
            $message = "API request failed: {$endpoint}. ";
            if (isset($errorBody['mensaje'])) {
                $message .= "Reason: " . $errorBody['mensaje'];
            } elseif (isset($errorBody['message'])) {
                $message .= "Reason: " . $errorBody['message'];
            }

            throw new PaymentGatewayException($message, 0, $e, $errorBody, $statusCode);
        }
    }

    /**
     * Masks sensitive data like credit card numbers for logging purposes.
     */
    private function maskSensitiveData(array $data): array
    {
        $masked = $data;
        if (isset($masked['numeroTarjeta'])) {
            $masked['numeroTarjeta'] = substr($masked['numeroTarjeta'], 0, 4) . '********' . substr($masked['numeroTarjeta'], -4);
        }
        if (isset($masked['cvv'])) {
            $masked['cvv'] = '***';
        }
        return $masked;
    }

    /**
     * Create a payment link (Enlace de Pago)
     * 
     * @see https://docs.wompi.sv/metodos-api/enlace-de-pago
     */
    public function createPaymentLink(array|PaymentLinkRequestDTO $data): PaymentLinkResponseDTO|array
    {
        $payload = $data instanceof PaymentLinkRequestDTO ? $data->toArray() : $data;
        $response = $this->makeAuthenticatedRequest('post', 'EnlacePago', $payload);
        
        return new PaymentLinkResponseDTO($response);
    }

    /**
     * Create a 3DS purchase transaction (Transacción Compra con 3DS)
     * 
     * @see https://docs.wompi.sv/metodos-api/crear-transaccion-compra-3ds
     */
    public function createTransaction3DS(array|Transaction3DSRequestDTO $data): TransactionResponseDTO|array
    {
        $payload = $data instanceof Transaction3DSRequestDTO ? $data->toArray() : $data;
        $response = $this->makeAuthenticatedRequest('post', 'Transaccion', $payload);
        
        return new TransactionResponseDTO($response);
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
    public function tokenizeCard(array|TokenizeCardRequestDTO $data): TokenizedCardResponseDTO|array
    {
        $payload = $data instanceof TokenizeCardRequestDTO ? $data->toArray() : $data;
        $response = $this->makeAuthenticatedRequest('post', 'Tokenizacion', $payload);
        
        return new TokenizedCardResponseDTO($response);
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

        // Dispatch general webhook received event
        event(new WompiWebhookReceived($webhookData));
        
        // Dispatch specific payment processed event with success boolean
        $isSuccessful = $this->isSuccessfulPayment($webhookData);
        event(new WompiPaymentProcessed($webhookData, $isSuccessful));

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
