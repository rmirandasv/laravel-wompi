<?php

namespace Rmirandasv\Wompi\DTOs\Requests\Concerns;

use Rmirandasv\Wompi\Exceptions\WompiValidationException;

/**
 * Shared validation helpers for the request DTOs.
 */
trait ValidatesPayload
{
    /**
     * @var array<string, string[]>
     */
    private array $payloadErrors = [];

    /**
     * Record a validation error for a field.
     */
    protected function addError(string $field, string $message): void
    {
        $this->payloadErrors[$field][] = $message;
    }

    /**
     * Throw the collected errors, if any.
     */
    protected function throwIfInvalid(string $message): void
    {
        if ($this->payloadErrors !== []) {
            throw new WompiValidationException($message, $this->payloadErrors);
        }
    }

    /**
     * Whether a value is present and not blank.
     */
    protected static function filled(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }

    /**
     * Read a trimmed string from an array.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function stringFrom(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        if ($value === null || is_array($value) || is_object($value)) {
            return $default;
        }

        return trim((string) $value);
    }

    /**
     * Read an optional string from an array.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function nullableStringFrom(array $data, string $key): ?string
    {
        $value = self::stringFrom($data, $key);

        return $value === '' ? null : $value;
    }

    /**
     * Read a float from an array.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function floatFrom(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Read an int from an array.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function intFrom(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Read an array from an array.
     *
     * @param  array<string, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected static function arrayFrom(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * Remove the given keys from an array.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    protected static function except(array $data, array $keys): array
    {
        return array_diff_key($data, array_flip($keys));
    }

    /**
     * Drop the keys whose value is null, so optional fields are not sent.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function withoutNulls(array $data): array
    {
        return array_filter($data, static fn ($value) => $value !== null);
    }
}
