<?php

namespace Rmirandasv\Wompi\Contracts;

use Illuminate\Http\Request;
use Rmirandasv\Wompi\DTOs\Requests\PaymentLinkRequestDTO;
use Rmirandasv\Wompi\DTOs\Requests\RecurringPaymentLinkRequestDTO;
use Rmirandasv\Wompi\DTOs\Requests\TokenizeCardRequestDTO;
use Rmirandasv\Wompi\DTOs\Requests\Transaction3DSRequestDTO;
use Rmirandasv\Wompi\DTOs\Responses\PaymentLinkResponseDTO;
use Rmirandasv\Wompi\DTOs\Responses\RecurringPaymentLinkResponseDTO;
use Rmirandasv\Wompi\DTOs\Responses\TokenizedCardResponseDTO;
use Rmirandasv\Wompi\DTOs\Responses\TransactionResponseDTO;

interface WompiClientInterface
{
    public function createPaymentLink(array|PaymentLinkRequestDTO $data): PaymentLinkResponseDTO|array;
    
    public function createTransaction3DS(array|Transaction3DSRequestDTO $data): TransactionResponseDTO|array;
    
    public function getAplicativoData(): array;
    
    public function tokenizeCard(array|TokenizeCardRequestDTO $data): TokenizedCardResponseDTO|array;
    
    public function getTokenizedCard(string $tokenId): array;
    
    public function deleteTokenizedCard(string $tokenId): array;
    
    public function createRecurringCharge(array $data): array;
    
    public function createRecurringPaymentLink(array|RecurringPaymentLinkRequestDTO $data): RecurringPaymentLinkResponseDTO|array;
    
    public function getRecurringPaymentLink(string $id): array;
    
    public function getRecurringPaymentLinks(): array;
    
    public function updateRecurringPaymentLink(string $id, array $data): array;
    
    public function deactivateRecurringPaymentLink(string $id): array;
    
    public function getRecurringPaymentLinkSubscribers(string $id): array;
    
    public function executeTestTransaction(array $data): array;
    
    public function validateWebhookSignature(string $body, string $receivedHash): bool;
    
    public function validateWebhookRequest(Request $request): array;
    
    public function validateRedirectParams(array $params, string $receivedHash): bool;
    
    public function isSuccessfulPayment(array $webhookData): bool;
}
