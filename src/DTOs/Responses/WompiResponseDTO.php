<?php

namespace Rmirandasv\Wompi\DTOs\Responses;

abstract class WompiResponseDTO implements \ArrayAccess
{
    public function __construct(protected array $data = []) {}

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
        // Read-only response
    }

    public function offsetUnset(mixed $offset): void
    {
        // Read-only response
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }
}
