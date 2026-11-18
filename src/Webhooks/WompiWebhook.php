<?php

namespace Rmirandasv\Wompi\Webhooks;

use Rmirandasv\Wompi\Enums\FormaPago;
use Rmirandasv\Wompi\Enums\ResultadoTransaccion;

/**
 * A normalized Wompi webhook payload.
 *
 * Wompi sends the webhook body in PascalCase while the API responses use
 * camelCase. This object exposes the same canonical camelCase accessors for
 * both, so the application never has to care which one it received.
 *
 * The original payload is always available through raw().
 *
 * @see https://docs.wompi.sv/webhook/definicion-webhook
 */
final class WompiWebhook
{
    /**
     * @param  array<array-key, mixed>  $raw
     * @param  array<string, mixed>  $canonical
     */
    public function __construct(
        private readonly array $raw,
        private readonly array $canonical = [],
    ) {
    }

    /**
     * The untouched payload as received from Wompi.
     *
     * @return array<array-key, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * The canonical (camelCase) representation of the payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->canonical;
    }

    /**
     * The unique transaction identifier.
     */
    public function idTransaccion(): ?string
    {
        return $this->canonical['idTransaccion'] ?? null;
    }

    /**
     * Alias of idTransaccion().
     */
    public function transactionId(): ?string
    {
        return $this->idTransaccion();
    }

    /**
     * The amount of the transaction.
     */
    public function amount(): int|float|string|null
    {
        return $this->canonical['monto'] ?? null;
    }

    /**
     * Alias of amount().
     */
    public function monto(): int|float|string|null
    {
        return $this->amount();
    }

    /**
     * The raw transaction result.
     *
     * The webhook sends the string "ExitosaAprobada" while the API responses
     * send an integer enumeration. Both are preserved as they were received.
     */
    public function result(): mixed
    {
        return $this->canonical['resultadoTransaccion'] ?? null;
    }

    /**
     * The transaction result as an enum, when the API sent the integer form.
     */
    public function resultEnum(): ?ResultadoTransaccion
    {
        $result = $this->result();

        return is_int($result) ? ResultadoTransaccion::fromRaw($result) : null;
    }

    /**
     * Whether the transaction was approved.
     *
     * The "esAprobada" boolean is authoritative. When it is absent, the
     * webhook string result is used.
     */
    public function isApproved(): bool
    {
        $approved = $this->canonical['esAprobada'] ?? null;

        if (is_bool($approved)) {
            return $approved;
        }

        $result = $this->result();

        if (is_string($result)) {
            return $result === 'ExitosaAprobada';
        }

        // The API also models the result as an integer enumeration whose
        // mapping Wompi does not document. Staying conservative here means a
        // transaction is never marked as paid without a positive signal.
        return false;
    }

    /**
     * Whether the transaction was a real (production) charge.
     *
     * A test transaction was never charged to the customer, so it must never
     * mark an order as paid.
     */
    public function isProduction(): bool
    {
        $production = $this->canonical['esProductiva'] ?? null;

        return is_bool($production) ? $production : true;
    }

    /**
     * Whether this webhook describes a test transaction.
     */
    public function isTestTransaction(): bool
    {
        return ! $this->isProduction();
    }

    /**
     * Whether the payment can be considered successful.
     *
     * A transaction is only successful when it was approved *and* it was a
     * real charge.
     */
    public function isSuccessful(): bool
    {
        return $this->isApproved() && $this->isProduction();
    }

    /**
     * The payment method used, e.g. "TarjetaCreditoDebito" or "PagoNormal".
     */
    public function paymentMethod(): ?string
    {
        return $this->canonical['formaPagoUtilizada'] ?? null;
    }

    /**
     * The payment method as an enum, when it matches a known value.
     */
    public function paymentMethodEnum(): ?FormaPago
    {
        return FormaPago::fromRaw($this->paymentMethod());
    }

    /**
     * The Wompi module that produced the transaction, e.g. "EnlacePago".
     */
    public function module(): ?string
    {
        return $this->canonical['moduloUtilizado'] ?? null;
    }

    /**
     * The authorization code returned by the acquirer.
     */
    public function authorizationCode(): ?string
    {
        return $this->canonical['codigoAutorizacion'] ?? null;
    }

    /**
     * The identifier of the payment attempt.
     */
    public function paymentAttemptId(): ?string
    {
        return $this->canonical['idIntentoPago'] ?? null;
    }

    /**
     * The amount of items purchased.
     */
    public function quantity(): ?int
    {
        return $this->canonical['cantidad'] ?? null;
    }

    /**
     * When the transaction took place.
     */
    public function occurredAt(): ?string
    {
        return $this->canonical['fechaTransaccion'] ?? null;
    }

    /**
     * The Wompi account identifier.
     */
    public function accountId(): ?string
    {
        return $this->canonical['idCuenta'] ?? null;
    }

    /**
     * The merchant reference ("identificadorEnlaceComercio"), which is where
     * the order id is usually stored.
     */
    public function reference(): ?string
    {
        return $this->canonical['identificadorEnlaceComercio'] ?? null;
    }

    /**
     * The internal Wompi identifier of the payment link.
     */
    public function paymentLinkId(): int|string|null
    {
        return $this->canonical['idEnlace'] ?? null;
    }

    /**
     * The name of the product that was purchased.
     */
    public function productName(): ?string
    {
        return $this->canonical['nombreProducto'] ?? null;
    }

    /**
     * The customer information sent by Wompi.
     *
     * @return array<array-key, mixed>
     */
    public function customer(): array
    {
        return $this->canonical['cliente'] ?? [];
    }

    /**
     * The additional data attached to the transaction.
     *
     * @return array<array-key, mixed>
     */
    public function additionalData(): array
    {
        return $this->canonical['datosAdicionales'] ?? [];
    }

    /**
     * The Wompi application information.
     *
     * @return array<array-key, mixed>
     */
    public function aplicativo(): array
    {
        return $this->canonical['aplicativo'] ?? [];
    }

    /**
     * A stable key that identifies this webhook delivery.
     *
     * Wompi retries failed webhook deliveries, so the same payment can be
     * delivered more than once. Use this key to make the handler idempotent.
     */
    public function idempotencyKey(): string
    {
        return $this->idTransaccion().':'.($this->paymentAttemptId() ?? '');
    }
}
