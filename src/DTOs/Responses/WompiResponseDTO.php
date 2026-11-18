<?php

namespace Rmirandasv\Wompi\DTOs\Responses;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Base class for every Wompi response.
 *
 * The DTOs expose the raw Wompi payload, so any field returned by the API is
 * reachable even when it is not documented yet. They also behave like an
 * array, an Arrayable and a JsonSerializable, so they can be returned
 * directly from a controller.
 *
 * @implements ArrayAccess<string, mixed>
 */
abstract class WompiResponseDTO implements ArrayAccess, Arrayable, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(protected array $data = [])
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(array $data = []): static
    {
        return new static($data);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // Responses are read only.
    }

    public function offsetUnset(mixed $offset): void
    {
        // Responses are read only.
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return $this->data;
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Read a field with a fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Whether the field exists in the payload.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Whether the payload is empty.
     */
    public function isEmpty(): bool
    {
        return $this->data === [];
    }
}
