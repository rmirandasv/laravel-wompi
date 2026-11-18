<?php

namespace Rmirandasv\Wompi\Events;

use Rmirandasv\Wompi\Webhooks\WompiWebhook;

/**
 * Dispatched every time a Wompi webhook is received and validated.
 */
class WompiWebhookReceived
{
    /**
     * @param  array<array-key, mixed>  $payload  The raw payload as sent by Wompi.
     */
    public function __construct(
        public readonly array $payload,
        public readonly WompiWebhook $webhook,
    ) {
    }
}
