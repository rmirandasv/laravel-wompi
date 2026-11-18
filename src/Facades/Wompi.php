<?php

namespace Rmirandasv\Wompi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Rmirandasv\Wompi\DTOs\Responses\PaymentLinkResponseDTO createPaymentLink(array|\Rmirandasv\Wompi\DTOs\Requests\PaymentLinkRequestDTO $data) Create a payment link (Enlace de Pago)
 * @method static \Rmirandasv\Wompi\DTOs\Responses\PagedResultDTO listPaymentLinks(array $filters = []) List the payment links of the account
 * @method static \Rmirandasv\Wompi\DTOs\Responses\PaymentLinkResponseDTO getPaymentLink(int|string $id) Get a payment link by its id
 * @method static \Rmirandasv\Wompi\DTOs\Responses\PaymentLinkResponseDTO updatePaymentLink(int|string $id, array|\Rmirandasv\Wompi\DTOs\Requests\PaymentLinkRequestDTO $data) Update a payment link
 * @method static \Rmirandasv\Wompi\DTOs\Responses\PaymentLinkResponseDTO activatePaymentLink(int|string $id) Reactivate a payment link
 * @method static \Rmirandasv\Wompi\DTOs\Responses\PaymentLinkResponseDTO deactivatePaymentLink(int|string $id) Deactivate a payment link
 * @method static \Rmirandasv\Wompi\DTOs\Responses\PaymentLinkResponseDTO addPaymentLinkImage(int|string $id, string $path, bool $isPrincipal = false) Upload an image for a payment link
 * @method static void deletePaymentLinkImage(int|string $id, string $url) Delete an image of a payment link
 * @method static \Rmirandasv\Wompi\DTOs\Responses\TransactionResponseDTO createTransaction3DS(array|\Rmirandasv\Wompi\DTOs\Requests\Transaction3DSRequestDTO $data) Create a 3DS purchase transaction
 * @method static \Rmirandasv\Wompi\DTOs\Responses\TransactionResponseDTO createTokenizedTransaction(array|\Rmirandasv\Wompi\DTOs\Requests\TokenizedTransactionRequestDTO $data) Charge an already tokenized card without 3D Secure
 * @method static \Rmirandasv\Wompi\DTOs\Responses\TransactionDetailDTO getTransaction(string $id) Get a transaction by its id
 * @method static \Rmirandasv\Wompi\DTOs\Responses\PagedResultDTO listTransactions(array $filters = []) List the transactions of the account
 * @method static string getTransactionReceipt(string $id) Get the receipt of a transaction as a raw string
 * @method static \Illuminate\Http\Client\Response getTransactionReceiptResponse(string $id) Get the receipt of a transaction as an HTTP response
 * @method static \Rmirandasv\Wompi\DTOs\Responses\DailyTransactionStatsDTO getDailyTransactionStats(array $filters = []) Get the daily transaction statistics
 * @method static \Rmirandasv\Wompi\DTOs\Responses\TokenizedCardResponseDTO tokenizeCard(array|\Rmirandasv\Wompi\DTOs\Requests\TokenizeCardRequestDTO $data) Tokenize a card for future use
 * @method static \Rmirandasv\Wompi\DTOs\Responses\PagedResultDTO listTokenizedCards(int $page = 1, int $perPage = 50) List the tokenized cards of the account
 * @method static void deleteTokenizedCard(string $tokenId) Delete a tokenized card
 * @method static \Rmirandasv\Wompi\DTOs\Responses\RecurringPaymentLinkResponseDTO createRecurringPaymentLink(array|\Rmirandasv\Wompi\DTOs\Requests\RecurringPaymentLinkRequestDTO $data) Create a recurring payment link (subscription)
 * @method static \Rmirandasv\Wompi\DTOs\Responses\PagedResultDTO listRecurringPaymentLinks(array $filters = []) List the recurring payment links
 * @method static \Rmirandasv\Wompi\DTOs\Responses\RecurringPaymentLinkResponseDTO getRecurringPaymentLink(string $id) Get a recurring payment link
 * @method static \Rmirandasv\Wompi\DTOs\Responses\RecurringPaymentLinkResponseDTO updateRecurringPaymentLink(string $id, array $data) Update a recurring payment link
 * @method static \Rmirandasv\Wompi\DTOs\Responses\RecurringPaymentLinkResponseDTO deactivateRecurringPaymentLink(string $id) Deactivate a recurring payment link
 * @method static \Rmirandasv\Wompi\DTOs\Responses\PagedResultDTO listRecurringSubscriptions(string $id, array $filters = []) List the subscriptions of a recurring payment link
 * @method static \Rmirandasv\Wompi\DTOs\Responses\AplicativoResponseDTO getAplicativoData() Get the application configuration and capabilities
 * @method static \Rmirandasv\Wompi\DTOs\Responses\AplicativoResponseDTO getAplicativoById(string $id) Get an application by its id
 * @method static array getAccount() Get the Wompi account of the application
 * @method static array getRegions() Get the regions and countries accepted by the API
 * @method static bool validateWebhookSignature(string $body, string $receivedHash) Validate the webhook HMAC signature
 * @method static \Rmirandasv\Wompi\Webhooks\WompiWebhook handleWebhook(\Illuminate\Http\Request $request) Validate, normalize and dispatch a webhook
 * @method static \Rmirandasv\Wompi\Webhooks\WompiWebhook validateWebhookRequest(\Illuminate\Http\Request $request) Validate, normalize and dispatch a webhook
 * @method static bool validateRedirectParams(array $params, string $receivedHash) Validate the redirect URL parameters
 * @method static bool isSuccessfulPayment(array $webhookData) Check whether a webhook describes a successful payment
 * @method static bool isRedirectApproved(array $params) Check whether the redirect parameters describe an approved transaction
 *
 * @see \Rmirandasv\Wompi\Contracts\WompiClientInterface
 */
class Wompi extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'wompi';
    }
}
