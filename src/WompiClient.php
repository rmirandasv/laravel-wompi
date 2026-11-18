<?php

namespace Rmirandasv\Wompi;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Rmirandasv\Wompi\Contracts\WompiClientInterface;
use Rmirandasv\Wompi\DTOs\Requests\PaymentLinkRequestDTO;
use Rmirandasv\Wompi\DTOs\Requests\RecurringPaymentLinkRequestDTO;
use Rmirandasv\Wompi\DTOs\Requests\TokenizeCardRequestDTO;
use Rmirandasv\Wompi\DTOs\Requests\TokenizedTransactionRequestDTO;
use Rmirandasv\Wompi\DTOs\Requests\Transaction3DSRequestDTO;
use Rmirandasv\Wompi\DTOs\Responses\AplicativoResponseDTO;
use Rmirandasv\Wompi\DTOs\Responses\DailyTransactionStatsDTO;
use Rmirandasv\Wompi\DTOs\Responses\PagedResultDTO;
use Rmirandasv\Wompi\DTOs\Responses\PaymentLinkResponseDTO;
use Rmirandasv\Wompi\DTOs\Responses\RecurringPaymentLinkResponseDTO;
use Rmirandasv\Wompi\DTOs\Responses\TokenizedCardResponseDTO;
use Rmirandasv\Wompi\DTOs\Responses\TransactionDetailDTO;
use Rmirandasv\Wompi\DTOs\Responses\TransactionResponseDTO;
use Rmirandasv\Wompi\Exceptions\ConfigurationException;
use Rmirandasv\Wompi\Http\WompiAuthenticator;
use Rmirandasv\Wompi\Http\WompiTransport;
use Rmirandasv\Wompi\Support\PayloadNormalizer;
use Rmirandasv\Wompi\Webhooks\WompiWebhook;
use Rmirandasv\Wompi\Webhooks\WompiWebhookHandler;

/**
 * The Wompi API client.
 *
 * Every path used here is documented in the Wompi Swagger:
 * https://api.wompi.sv/index.html
 */
class WompiClient implements WompiClientInterface
{
    private readonly WompiAuthenticator $authenticator;

    private readonly WompiTransport $transport;

    private readonly WompiWebhookHandler $webhookHandler;

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $authUrl,
        private readonly string $audience,
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
        private readonly int $retry = 2,
        private readonly bool $logPayloads = false,
        private readonly ?string $cacheStore = null,
        private readonly int $ttlMargin = 60,
    ) {
        if (! $this->clientId || ! $this->clientSecret) {
            throw new ConfigurationException('Wompi credentials are not set');
        }

        $this->authenticator = new WompiAuthenticator(
            authUrl: $this->authUrl,
            audience: $this->audience,
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            ttlMargin: $this->ttlMargin,
            cacheStore: $this->cacheStore,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
        );

        $this->transport = new WompiTransport(
            apiUrl: $this->apiUrl,
            authenticator: $this->authenticator,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
            retry: $this->retry,
            logPayloads: $this->logPayloads,
        );

        $this->webhookHandler = new WompiWebhookHandler($this->clientSecret);
    }

    /**
     * Build the client from the application configuration.
     */
    public static function fromConfig(): self
    {
        return new self(
            apiUrl: (string) config('wompi.api_url'),
            authUrl: (string) config('wompi.auth_url'),
            audience: (string) config('wompi.audience', 'wompi_api'),
            clientId: config('wompi.client_id'),
            clientSecret: config('wompi.client_secret'),
            timeout: (int) config('wompi.timeout', 30),
            connectTimeout: (int) config('wompi.connect_timeout', 10),
            retry: (int) config('wompi.retry', 2),
            logPayloads: (bool) config('wompi.log_payloads', false),
            cacheStore: config('wompi.token_cache.store'),
            ttlMargin: (int) config('wompi.token_cache.ttl_margin', 60),
        );
    }

    // -----------------------------------------------------------------
    // Payment links
    // -----------------------------------------------------------------

    public function createPaymentLink(array|PaymentLinkRequestDTO $data): PaymentLinkResponseDTO
    {
        $payload = $data instanceof PaymentLinkRequestDTO ? $data->toArray() : $data;

        return new PaymentLinkResponseDTO($this->transport->post('EnlacePago', $payload));
    }

    public function listPaymentLinks(array $filters = []): PagedResultDTO
    {
        return new PagedResultDTO($this->transport->get('EnlacePago', $filters));
    }

    public function getPaymentLink(int|string $id): PaymentLinkResponseDTO
    {
        return new PaymentLinkResponseDTO($this->transport->get("EnlacePago/{$id}"));
    }

    public function updatePaymentLink(int|string $id, array|PaymentLinkRequestDTO $data): PaymentLinkResponseDTO
    {
        $payload = $data instanceof PaymentLinkRequestDTO ? $data->toArray() : $data;

        return new PaymentLinkResponseDTO($this->transport->put("EnlacePago/{$id}", $payload));
    }

    public function activatePaymentLink(int|string $id): PaymentLinkResponseDTO
    {
        return new PaymentLinkResponseDTO($this->transport->put("EnlacePago/{$id}/activar"));
    }

    public function deactivatePaymentLink(int|string $id): PaymentLinkResponseDTO
    {
        return new PaymentLinkResponseDTO($this->transport->put("EnlacePago/{$id}/desactivar"));
    }

    public function addPaymentLinkImage(int|string $id, string $path, bool $isPrincipal = false): PaymentLinkResponseDTO
    {
        $response = $this->transport->postMultipart(
            "EnlacePago/{$id}/imagen",
            [],
            ['imagen' => $path],
            ['esPrincipal' => $isPrincipal ? 'true' : 'false']
        );

        return new PaymentLinkResponseDTO($response);
    }

    public function deletePaymentLinkImage(int|string $id, string $url): void
    {
        $this->transport->delete("EnlacePago/{$id}/imagen", [], ['url' => $url]);
    }

    // -----------------------------------------------------------------
    // Transactions
    // -----------------------------------------------------------------

    public function createTransaction3DS(array|Transaction3DSRequestDTO $data): TransactionResponseDTO
    {
        $payload = $data instanceof Transaction3DSRequestDTO ? $data->toArray() : $data;

        return new TransactionResponseDTO($this->transport->post('TransaccionCompra/3Ds', $payload));
    }

    public function createTokenizedTransaction(array|TokenizedTransactionRequestDTO $data): TransactionResponseDTO
    {
        $payload = $data instanceof TokenizedTransactionRequestDTO ? $data->toArray() : $data;

        return new TransactionResponseDTO($this->transport->post('TransaccionCompra/TokenizadaSin3Ds', $payload));
    }

    public function getTransaction(string $id): TransactionDetailDTO
    {
        return new TransactionDetailDTO($this->transport->get("TransaccionCompra/{$id}"));
    }

    public function listTransactions(array $filters = []): PagedResultDTO
    {
        return new PagedResultDTO($this->transport->get('TransaccionCompra', $filters));
    }

    public function getTransactionReceipt(string $id): string
    {
        return $this->transport->getRaw("TransaccionCompra/{$id}/comprobante");
    }

    public function getTransactionReceiptResponse(string $id): Response
    {
        return $this->transport->getResponse("TransaccionCompra/{$id}/comprobante");
    }

    public function getDailyTransactionStats(array $filters = []): DailyTransactionStatsDTO
    {
        return new DailyTransactionStatsDTO($this->transport->get('TransaccionCompra/estadistica/diaria', $filters));
    }

    // -----------------------------------------------------------------
    // Tokenization
    // -----------------------------------------------------------------

    public function tokenizeCard(array|TokenizeCardRequestDTO $data): TokenizedCardResponseDTO
    {
        $payload = $data instanceof TokenizeCardRequestDTO ? $data->toArray() : $data;

        return new TokenizedCardResponseDTO($this->transport->post('Tokenizacion', $payload));
    }

    public function listTokenizedCards(int $page = 1, int $perPage = 50): PagedResultDTO
    {
        return new PagedResultDTO($this->transport->get('Tokenizacion', [
            'Pagina' => $page,
            'CantidadPagina' => $perPage,
        ]));
    }

    public function deleteTokenizedCard(string $tokenId): void
    {
        $this->transport->delete("Tokenizacion/{$tokenId}");
    }

    // -----------------------------------------------------------------
    // Subscriptions
    // -----------------------------------------------------------------

    public function createRecurringPaymentLink(array|RecurringPaymentLinkRequestDTO $data): RecurringPaymentLinkResponseDTO
    {
        $payload = $data instanceof RecurringPaymentLinkRequestDTO ? $data->toArray() : $data;

        return new RecurringPaymentLinkResponseDTO($this->transport->post('EnlacePagoRecurrente', $payload));
    }

    public function listRecurringPaymentLinks(array $filters = []): PagedResultDTO
    {
        return new PagedResultDTO($this->transport->get('EnlacePagoRecurrente', $filters));
    }

    public function getRecurringPaymentLink(string $id): RecurringPaymentLinkResponseDTO
    {
        return new RecurringPaymentLinkResponseDTO($this->transport->get("EnlacePagoRecurrente/{$id}"));
    }

    public function updateRecurringPaymentLink(string $id, array $data): RecurringPaymentLinkResponseDTO
    {
        return new RecurringPaymentLinkResponseDTO($this->transport->put("EnlacePagoRecurrente/{$id}", $data));
    }

    public function deactivateRecurringPaymentLink(string $id): RecurringPaymentLinkResponseDTO
    {
        return new RecurringPaymentLinkResponseDTO($this->transport->post("EnlacePagoRecurrente/{$id}"));
    }

    public function listRecurringSubscriptions(string $id, array $filters = []): PagedResultDTO
    {
        return new PagedResultDTO($this->transport->get("EnlacePagoRecurrente/{$id}/suscripciones", $filters));
    }

    // -----------------------------------------------------------------
    // Business
    // -----------------------------------------------------------------

    public function getAplicativoData(): AplicativoResponseDTO
    {
        return new AplicativoResponseDTO($this->transport->get('Aplicativo'));
    }

    public function getAplicativoById(string $id): AplicativoResponseDTO
    {
        return new AplicativoResponseDTO($this->transport->get("Aplicativo/{$id}"));
    }

    public function getAccount(): array
    {
        return $this->transport->get('Cuenta');
    }

    public function getRegions(): array
    {
        return $this->transport->get('api/Regiones');
    }

    // -----------------------------------------------------------------
    // Webhooks and redirect URLs
    // -----------------------------------------------------------------

    public function validateWebhookSignature(string $body, string $receivedHash): bool
    {
        return $this->webhookHandler->validateSignature($body, $receivedHash);
    }

    public function handleWebhook(Request $request): WompiWebhook
    {
        return $this->webhookHandler->handle($request);
    }

    public function validateWebhookRequest(Request $request): WompiWebhook
    {
        return $this->webhookHandler->handle($request);
    }

    public function validateRedirectParams(array $params, string $receivedHash): bool
    {
        if ($this->clientSecret === '') {
            throw new ConfigurationException('Wompi client secret is not set');
        }

        if ($receivedHash === '') {
            return false;
        }

        $values = $this->redirectParamValues($params);

        // A missing parameter means the redirect was tampered with or comes
        // from another source, so it is rejected instead of being validated
        // against a shorter string.
        if ($values === null) {
            return false;
        }

        $calculated = hash_hmac('sha256', implode('', $values), $this->clientSecret);

        return hash_equals($calculated, $receivedHash);
    }

    public function isSuccessfulPayment(array $webhookData): bool
    {
        return PayloadNormalizer::normalize($webhookData)->isSuccessful();
    }

    public function isRedirectApproved(array $params): bool
    {
        $approved = PayloadNormalizer::pickBool($params, 'esAprobada', 'EsAprobada');

        return $approved === true;
    }

    /**
     * The parameters of the redirect URL, in the order Wompi concatenates
     * them before signing.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, string>|null
     */
    private function redirectParamValues(array $params): ?array
    {
        $keys = $this->isPaymentLinkRedirect($params)
            ? ['identificadorEnlaceComercio', 'idTransaccion', 'idEnlace', 'monto']
            : ['idTransaccion', 'monto', 'esReal', 'formaPago', 'esAprobada', 'codigoAutorizacion', 'mensaje'];

        $values = [];

        foreach ($keys as $key) {
            $value = PayloadNormalizer::pick($params, $key);

            if ($value === null) {
                return null;
            }

            if (is_bool($value)) {
                $values[] = $value ? 'true' : 'false';

                continue;
            }

            if (is_array($value) || is_object($value)) {
                return null;
            }

            $values[] = (string) $value;
        }

        return $values;
    }

    /**
     * A payment link redirect carries the merchant reference, the API one does
     * not.
     *
     * @param  array<string, mixed>  $params
     */
    private function isPaymentLinkRedirect(array $params): bool
    {
        $reference = PayloadNormalizer::pick($params, 'identificadorEnlaceComercio', 'IdentificadorEnlaceComercio');

        return $reference !== null && $reference !== '';
    }
}
