<?php

namespace Rmirandasv\Wompi\Contracts;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
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
use Rmirandasv\Wompi\Webhooks\WompiWebhook;

/**
 * The public API of the Wompi integration.
 *
 * Every method that talks to the API uses the paths documented in the Wompi
 * Swagger (https://api.wompi.sv/index.html).
 */
interface WompiClientInterface
{
    // ---------------------------------------------------------------------
    // Payment links
    // ---------------------------------------------------------------------

    /**
     * Create a payment link.
     *
     * @param  array<string, mixed>|PaymentLinkRequestDTO  $data
     */
    public function createPaymentLink(array|PaymentLinkRequestDTO $data): PaymentLinkResponseDTO;

    /**
     * List the payment links of the account.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listPaymentLinks(array $filters = []): PagedResultDTO;

    /**
     * Get a payment link by its id.
     */
    public function getPaymentLink(int|string $id): PaymentLinkResponseDTO;

    /**
     * Update a payment link.
     *
     * @param  array<string, mixed>|PaymentLinkRequestDTO  $data
     */
    public function updatePaymentLink(int|string $id, array|PaymentLinkRequestDTO $data): PaymentLinkResponseDTO;

    /**
     * Reactivate a payment link so it can be paid again.
     */
    public function activatePaymentLink(int|string $id): PaymentLinkResponseDTO;

    /**
     * Deactivate a payment link so it can no longer be paid.
     */
    public function deactivatePaymentLink(int|string $id): PaymentLinkResponseDTO;

    /**
     * Upload an image for a payment link.
     */
    public function addPaymentLinkImage(int|string $id, string $path, bool $isPrincipal = false): PaymentLinkResponseDTO;

    /**
     * Delete an image of a payment link.
     */
    public function deletePaymentLinkImage(int|string $id, string $url): void;

    // ---------------------------------------------------------------------
    // Transactions
    // ---------------------------------------------------------------------

    /**
     * Create a 3DS purchase transaction.
     *
     * @param  array<string, mixed>|Transaction3DSRequestDTO  $data
     */
    public function createTransaction3DS(array|Transaction3DSRequestDTO $data): TransactionResponseDTO;

    /**
     * Charge an already tokenized card without 3D Secure.
     *
     * @param  array<string, mixed>|TokenizedTransactionRequestDTO  $data
     */
    public function createTokenizedTransaction(array|TokenizedTransactionRequestDTO $data): TransactionResponseDTO;

    /**
     * Get a transaction by its id.
     */
    public function getTransaction(string $id): TransactionDetailDTO;

    /**
     * List the transactions of the account.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listTransactions(array $filters = []): PagedResultDTO;

    /**
     * Get the receipt of a transaction as a raw string.
     */
    public function getTransactionReceipt(string $id): string;

    /**
     * Get the receipt of a transaction as an HTTP response.
     */
    public function getTransactionReceiptResponse(string $id): Response;

    /**
     * Get the daily transaction statistics of the account.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getDailyTransactionStats(array $filters = []): DailyTransactionStatsDTO;

    // ---------------------------------------------------------------------
    // Tokenization
    // ---------------------------------------------------------------------

    /**
     * Tokenize a card so it can be charged later.
     *
     * @param  array<string, mixed>|TokenizeCardRequestDTO  $data
     */
    public function tokenizeCard(array|TokenizeCardRequestDTO $data): TokenizedCardResponseDTO;

    /**
     * List the tokenized cards of the account.
     */
    public function listTokenizedCards(int $page = 1, int $perPage = 50): PagedResultDTO;

    /**
     * Delete a tokenized card.
     */
    public function deleteTokenizedCard(string $tokenId): void;

    // ---------------------------------------------------------------------
    // Subscriptions (recurring payment links)
    // ---------------------------------------------------------------------

    /**
     * Create a recurring payment link the customers can subscribe to.
     *
     * @param  array<string, mixed>|RecurringPaymentLinkRequestDTO  $data
     */
    public function createRecurringPaymentLink(array|RecurringPaymentLinkRequestDTO $data): RecurringPaymentLinkResponseDTO;

    /**
     * List the recurring payment links of the account.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listRecurringPaymentLinks(array $filters = []): PagedResultDTO;

    /**
     * Get a recurring payment link by its id.
     */
    public function getRecurringPaymentLink(string $id): RecurringPaymentLinkResponseDTO;

    /**
     * Update a recurring payment link.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRecurringPaymentLink(string $id, array $data): RecurringPaymentLinkResponseDTO;

    /**
     * Deactivate a recurring payment link.
     */
    public function deactivateRecurringPaymentLink(string $id): RecurringPaymentLinkResponseDTO;

    /**
     * List the subscriptions attached to a recurring payment link.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listRecurringSubscriptions(string $id, array $filters = []): PagedResultDTO;

    // ---------------------------------------------------------------------
    // Business
    // ---------------------------------------------------------------------

    /**
     * Get the configuration and capabilities of the application.
     */
    public function getAplicativoData(): AplicativoResponseDTO;

    /**
     * Get an application by its id.
     */
    public function getAplicativoById(string $id): AplicativoResponseDTO;

    /**
     * Get the Wompi account of the application.
     *
     * @return array<string, mixed>
     */
    public function getAccount(): array;

    /**
     * Get the regions and countries accepted by the API.
     *
     * @return array<array-key, mixed>
     */
    public function getRegions(): array;

    // ---------------------------------------------------------------------
    // Webhooks and redirect URLs
    // ---------------------------------------------------------------------

    /**
     * Validate the HMAC signature of a webhook body.
     */
    public function validateWebhookSignature(string $body, string $receivedHash): bool;

    /**
     * Validate a webhook request, normalize it and dispatch the events.
     */
    public function handleWebhook(Request $request): WompiWebhook;

    /**
     * Validate a webhook request, normalize it and dispatch the events.
     */
    public function validateWebhookRequest(Request $request): WompiWebhook;

    /**
     * Validate the HMAC signature of the redirect URL parameters.
     */
    public function validateRedirectParams(array $params, string $receivedHash): bool;

    /**
     * Whether a webhook payload describes a successful payment.
     *
     * A transaction is only successful when it was approved and it was a real
     * charge: a test transaction was never billed to the customer.
     *
     * @param  array<string, mixed>  $webhookData
     */
    public function isSuccessfulPayment(array $webhookData): bool;

    /**
     * Whether the redirect parameters describe an approved transaction.
     *
     * @param  array<string, mixed>  $params
     */
    public function isRedirectApproved(array $params): bool;
}
