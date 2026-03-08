<?php

namespace Rmirandasv\Wompi\DTOs\Requests;

class Transaction3DSRequestDTO
{
    public function __construct(
        public readonly float $monto,
        public readonly string $numeroTarjeta = '',
        public readonly string $cvv = '',
        public readonly string $mesExpiracion = '',
        public readonly string $anioExpiracion = '',
        public readonly array $extraData = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['monto'] ?? 0.0,
            $data['numeroTarjeta'] ?? '',
            $data['cvv'] ?? '',
            $data['mesExpiracion'] ?? '',
            $data['anioExpiracion'] ?? '',
            array_diff_key($data, array_flip(['monto', 'numeroTarjeta', 'cvv', 'mesExpiracion', 'anioExpiracion']))
        );
    }

    public function toArray(): array
    {
        return array_merge([
            'monto' => $this->monto,
            'numeroTarjeta' => $this->numeroTarjeta,
            'cvv' => $this->cvv,
            'mesExpiracion' => $this->mesExpiracion,
            'anioExpiracion' => $this->anioExpiracion,
        ], $this->extraData);
    }
}
