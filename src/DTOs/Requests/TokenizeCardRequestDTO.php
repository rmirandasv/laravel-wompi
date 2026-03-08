<?php

namespace Rmirandasv\Wompi\DTOs\Requests;

class TokenizeCardRequestDTO
{
    public function __construct(
        public readonly string $numeroTarjeta = '',
        public readonly string $mesExpiracion = '',
        public readonly string $anioExpiracion = '',
        public readonly string $cvv = '',
        public readonly array $extraData = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['numeroTarjeta'] ?? '',
            $data['mesExpiracion'] ?? '',
            $data['anioExpiracion'] ?? '',
            $data['cvv'] ?? '',
            array_diff_key($data, array_flip(['numeroTarjeta', 'mesExpiracion', 'anioExpiracion', 'cvv']))
        );
    }

    public function toArray(): array
    {
        return array_merge([
            'numeroTarjeta' => $this->numeroTarjeta,
            'mesExpiracion' => $this->mesExpiracion,
            'anioExpiracion' => $this->anioExpiracion,
            'cvv' => $this->cvv,
        ], $this->extraData);
    }
}
