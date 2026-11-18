<?php

namespace Rmirandasv\Wompi\Exceptions;

/**
 * Thrown when the OAuth2 client_credentials handshake with Wompi fails.
 *
 * Extends PaymentGatewayException so applications that already catch the
 * generic gateway error keep working.
 */
class WompiAuthenticationException extends PaymentGatewayException
{
    /**
     * @param  array<string, mixed>|null  $responseBody
     */
    public function __construct(
        string $message = 'Failed to authenticate with Wompi',
        int $code = 0,
        ?\Throwable $previous = null,
        ?array $responseBody = null,
        ?int $statusCode = null,
    ) {
        parent::__construct($message, $code, $previous, $responseBody, $statusCode);
    }
}
