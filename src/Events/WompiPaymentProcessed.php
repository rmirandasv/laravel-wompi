<?php

namespace Rmirandasv\Wompi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WompiPaymentProcessed
{
    use Dispatchable, SerializesModels;

    /**
     * @var array<string, mixed>
     */
    public array $payload;

    public bool $isSuccessful;

    public function __construct(array $payload, bool $isSuccessful)
    {
        $this->payload = $payload;
        $this->isSuccessful = $isSuccessful;
    }
}
