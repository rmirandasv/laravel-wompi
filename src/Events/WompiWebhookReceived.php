<?php

namespace Rmirandasv\Wompi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WompiWebhookReceived
{
    use Dispatchable, SerializesModels;

    /**
     * @var array<string, mixed>
     */
    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }
}
