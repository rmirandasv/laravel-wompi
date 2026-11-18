<?php

namespace Rmirandasv\Wompi\Events;

use Rmirandasv\Wompi\Webhooks\WompiWebhook;

/**
 * Dispatched every time a Wompi webhook is received and validated.
 *
 * The "isSuccessful" flag is true only when the transaction was approved
 * *and* it was a real charge. A test transaction was never billed to the
 * customer, so it must never mark an order as paid.
 */
class WompiPaymentProcessed
{
    /**
     * @param  array<array-key, mixed>  $payload  The raw payload as sent by Wompi.
     */
    public function __construct(
        public readonly array $payload,
        public readonly bool $isSuccessful,
        public readonly WompiWebhook $webhook,
    ) {
    }
}
