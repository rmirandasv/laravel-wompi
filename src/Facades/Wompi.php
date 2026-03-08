<?php

namespace Rmirandasv\Wompi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Rmirandasv\Wompi\DTOs\Responses\PaymentLinkResponseDTO|array createPaymentLink(array|\Rmirandasv\Wompi\DTOs\Requests\PaymentLinkRequestDTO $data) Create a payment link (Enlace de Pago)
 * @method static \Rmirandasv\Wompi\DTOs\Responses\TransactionResponseDTO|array createTransaction3DS(array|\Rmirandasv\Wompi\DTOs\Requests\Transaction3DSRequestDTO $data) Create a 3DS purchase transaction
 * @method static array getAplicativoData() Get aplicativo configuration and capabilities
 * @method static \Rmirandasv\Wompi\DTOs\Responses\TokenizedCardResponseDTO|array tokenizeCard(array|\Rmirandasv\Wompi\DTOs\Requests\TokenizeCardRequestDTO $data) Tokenize a card for future use
 * @method static array getTokenizedCard(string $tokenId) Get a tokenized card by ID
 * @method static array deleteTokenizedCard(string $tokenId) Delete a tokenized card
 * @method static array createRecurringCharge(array $data) Create a recurring charge using a tokenized card
 * @method static array executeTestTransaction(array $data) Execute test transactions
 * @method static bool validateWebhookSignature(string $body, string $receivedHash) Validate webhook HMAC signature
 * @method static array validateWebhookRequest(\Illuminate\Http\Request $request) Validate and parse webhook request
 * @method static bool validateRedirectParams(array $params, string $receivedHash) Validate redirect URL parameters
 * @method static bool isSuccessfulPayment(array $webhookData) Check if a webhook indicates a successful payment
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
