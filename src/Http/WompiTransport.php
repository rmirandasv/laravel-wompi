<?php

namespace Rmirandasv\Wompi\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;
use Rmirandasv\Wompi\Exceptions\WompiApiException;
use Rmirandasv\Wompi\Support\SensitiveDataMasker;

/**
 * Executes the HTTP calls against the Wompi API.
 *
 * Responsibilities:
 *
 *   - Attach the OAuth2 bearer token.
 *   - Retry transport failures for idempotent verbs only.
 *   - Recover from an expired token by invalidating it and retrying once.
 *   - Map Wompi error responses onto WompiApiException.
 *   - Never write card data to the logs.
 */
class WompiTransport
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly WompiAuthenticator $authenticator,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
        private readonly int $retry = 2,
        private readonly bool $logPayloads = false,
    ) {
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->json($this->send('GET', $endpoint, [], $query));
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $body = [], array $query = [], bool $idempotent = false): array
    {
        return $this->json($this->send('POST', $endpoint, $body, $query, $idempotent));
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function put(string $endpoint, array $body = [], array $query = [], bool $idempotent = true): array
    {
        return $this->json($this->send('PUT', $endpoint, $body, $query, $idempotent));
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function delete(string $endpoint, array $body = [], array $query = []): array
    {
        return $this->json($this->send('DELETE', $endpoint, $body, $query));
    }

    /**
     * Upload a file to an endpoint.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $files  Field name => path on disk.
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function postMultipart(string $endpoint, array $body, array $files, array $query = []): array
    {
        $pending = $this->pending();

        foreach ($files as $field => $path) {
            $pending = $pending->attach($field, $path);
        }

        $response = $pending->post($this->url($endpoint, $query), $body);

        $this->assertSuccessful($response, 'POST', $endpoint);

        return $this->json($response);
    }

    /**
     * Fetch a raw (binary) response, e.g. the transaction receipt.
     */
    public function getRaw(string $endpoint, array $query = []): string
    {
        return $this->send('GET', $endpoint, [], $query)->body();
    }

    /**
     * Fetch the full response so the application can control the content type
     * and the content disposition headers.
     */
    public function getResponse(string $endpoint, array $query = []): Response
    {
        return $this->send('GET', $endpoint, [], $query);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     */
    private function send(
        string $method,
        string $endpoint,
        array $body = [],
        array $query = [],
        bool $idempotent = false,
    ): Response {
        $url = $this->url($endpoint, $query);

        $startedAt = microtime(true);

        $this->logRequest($method, $endpoint, $url, $body);

        try {
            $response = $this->dispatch($method, $url, $body);
        } catch (ConnectionException $e) {
            Log::error("Wompi API request failed: {$method} {$endpoint}", [
                'error' => $e->getMessage(),
            ]);

            throw new PaymentGatewayException(
                "API request failed: {$endpoint}. Could not reach Wompi.",
                0,
                $e
            );
        }

        // An expired or revoked token is recoverable: drop it and try once
        // more with a fresh one. POST requests are only retried when the
        // caller states that the operation is idempotent, so that a customer
        // is never charged twice.
        if ($response->status() === 401 && $this->mayRetry($method, $idempotent)) {
            $this->authenticator->invalidate();

            try {
                $response = $this->dispatch($method, $url, $body);
            } catch (ConnectionException $e) {
                throw new PaymentGatewayException(
                    "API request failed: {$endpoint}. Could not reach Wompi.",
                    0,
                    $e
                );
            }
        }

        Log::debug("Wompi API response: {$method} {$endpoint}", [
            'status' => $response->status(),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        $this->assertSuccessful($response, $method, $endpoint);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function dispatch(string $method, string $url, array $body): Response
    {
        $pending = $this->pending();

        // Only idempotent verbs are retried automatically. Laravel's retry()
        // only reacts to transport level failures, never to HTTP error
        // responses, because we never call throw() on the pending request.
        if (in_array($method, ['GET', 'DELETE'], true) && $this->retry > 0) {
            $pending = $pending->retry(
                $this->retry,
                200,
                // Laravel hands a RequestException for HTTP error responses
                // and a ConnectionException for transport failures. Only the
                // latter is retried, so a failed GET is never hammered.
                fn ($exception) => $exception instanceof ConnectionException,
                // The last response is returned instead of being thrown, so
                // the error can be mapped onto WompiApiException.
                false
            );
        }

        return match ($method) {
            'GET' => $pending->get($url),
            'POST' => $pending->post($url, $body),
            'PUT' => $pending->put($url, $body),
            'DELETE' => $pending->delete($url, $body),
            default => throw new \InvalidArgumentException("Unsupported HTTP method [{$method}]"),
        };
    }

    private function pending(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->authenticator->token())
            ->acceptJson()
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout);
    }

    private function mayRetry(string $method, bool $idempotent): bool
    {
        return in_array($method, ['GET', 'DELETE', 'PUT'], true) || $idempotent;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function url(string $endpoint, array $query = []): string
    {
        $url = rtrim($this->apiUrl, '/').'/'.ltrim($endpoint, '/');

        if ($query !== []) {
            $url .= '?'.Arr::query($query);
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function logRequest(string $method, string $endpoint, string $url, array $body): void
    {
        Log::debug("Wompi API request: {$method} {$endpoint}", [
            'url' => $url,
            'payload' => $this->logPayloads
                ? SensitiveDataMasker::mask($body)
                : '[not logged]',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    private function assertSuccessful(Response $response, string $method, string $endpoint): void
    {
        if (! $response->failed()) {
            return;
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $messages = $this->extractMessages($body);
        $serviceError = isset($body['servicioError']) ? (string) $body['servicioError'] : null;
        $errorType = isset($body['subTipoError']) ? (string) $body['subTipoError'] : null;

        $message = "API request failed: {$endpoint}.";
        $message .= ' HTTP '.$response->status().'.';

        if ($messages !== []) {
            $message .= ' '.implode(' ', $messages);
        } elseif ($serviceError !== null) {
            $message .= ' '.$serviceError;
        }

        Log::error("Wompi API request failed: {$method} {$endpoint}", [
            'status' => $response->status(),
            'error_body' => SensitiveDataMasker::mask($body),
        ]);

        throw new WompiApiException(
            $message,
            $response->status(),
            null,
            $body,
            $response->status(),
            $messages,
            $serviceError,
            $errorType
        );
    }

    /**
     * Wompi reports failures with "mensajes" (an array). Older payloads and
     * other gateways use "mensaje" or "message".
     *
     * @param  array<string, mixed>  $body
     * @return array<string, string[]>
     */
    private function extractMessages(array $body): array
    {
        if (isset($body['mensajes']) && is_array($body['mensajes'])) {
            return array_values(array_map(
                static fn ($message) => (string) $message,
                $body['mensajes']
            ));
        }

        foreach (['mensaje', 'message'] as $key) {
            if (isset($body[$key]) && is_scalar($body[$key])) {
                return [(string) $body[$key]];
            }
        }

        return [];
    }
}
