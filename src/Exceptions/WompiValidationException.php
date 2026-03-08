<?php

namespace Rmirandasv\Wompi\Exceptions;

class WompiValidationException extends \Exception
{
    /**
     * @var array<string, string[]>
     */
    protected array $errors;

    public function __construct(string $message = "The given data was invalid.", array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get the validation errors.
     *
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
