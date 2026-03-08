<?php

namespace Rmirandasv\Wompi\Exceptions;

class PaymentGatewayException extends \Exception
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $responseBody = null;

    /**
     * @var int|null
     */
    protected ?int $statusCode = null;

    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null, ?array $responseBody = null, ?int $statusCode = null)
    {
        parent::__construct($message, $code, $previous);
        $this->responseBody = $responseBody;
        $this->statusCode = $statusCode;
    }

    /**
     * Get the JSON response body from Wompi error, if available.
     */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    /**
     * Get the HTTP status code of the Wompi error response, if available.
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}
