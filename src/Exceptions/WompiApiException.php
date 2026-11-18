<?php

namespace Rmirandasv\Wompi\Exceptions;

/**
 * Thrown when the Wompi API returns a non-successful HTTP status.
 *
 * Wompi reports failures with the "ApiErrorResult" shape:
 *
 *     {
 *       "servicioError": "...",
 *       "subTipoError": "...",
 *       "mensajes": ["...", "..."]
 *     }
 *
 * All three fields are preserved so that applications can react to the
 * specific error instead of parsing a message string.
 */
class WompiApiException extends PaymentGatewayException
{
    /**
     * @var array<string, string[]>
     */
    protected array $messages = [];

    protected ?string $serviceError = null;

    protected ?string $errorType = null;

    /**
     * @param  array<string, mixed>|null  $responseBody
     * @param  array<string, string[]>  $messages
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?array $responseBody = null,
        ?int $statusCode = null,
        array $messages = [],
        ?string $serviceError = null,
        ?string $errorType = null,
    ) {
        parent::__construct($message, $code, $previous, $responseBody, $statusCode);

        $this->messages = $messages;
        $this->serviceError = $serviceError;
        $this->errorType = $errorType;
    }

    /**
     * The individual error messages reported by Wompi ("mensajes").
     *
     * @return array<string, string[]>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * The service layer that produced the error ("servicioError").
     */
    public function getServiceError(): ?string
    {
        return $this->serviceError;
    }

    /**
     * The granular error identifier ("subTipoError").
     */
    public function getErrorType(): ?string
    {
        return $this->errorType;
    }
}
