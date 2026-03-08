<?php

namespace Rmirandasv\Wompi\DTOs\Requests;

class PaymentLinkRequestDTO
{
    public function __construct(
        public readonly float $monto,
        public readonly string $identificadorEnlaceComercio = '',
        public readonly string $nombreProducto = '',
        public readonly array $formaPago = [],
        public readonly array $configuracion = [],
        public readonly array $extraData = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['monto'] ?? 0.0,
            $data['identificadorEnlaceComercio'] ?? '',
            $data['nombreProducto'] ?? '',
            $data['formaPago'] ?? [],
            $data['configuracion'] ?? [],
            array_diff_key($data, array_flip(['monto', 'identificadorEnlaceComercio', 'nombreProducto', 'formaPago', 'configuracion']))
        );
    }

    public function toArray(): array
    {
        return array_merge([
            'monto' => $this->monto,
            'identificadorEnlaceComercio' => $this->identificadorEnlaceComercio,
            'nombreProducto' => $this->nombreProducto,
            'formaPago' => $this->formaPago,
            'configuracion' => $this->configuracion,
        ], $this->extraData);
    }
}
