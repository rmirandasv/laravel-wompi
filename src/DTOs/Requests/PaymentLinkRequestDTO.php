<?php

namespace Rmirandasv\Wompi\DTOs\Requests;

use Rmirandasv\Wompi\Exceptions\WompiValidationException;

class PaymentLinkRequestDTO
{
    public function __construct(
        public readonly float $monto,
        public readonly string $identificadorEnlaceComercio = '',
        public readonly string $nombreProducto = '',
        public readonly array $formaPago = [],
        public readonly array $configuracion = [],
        public readonly array $extraData = []
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        $errors = [];
        if ($this->monto <= 0) {
            $errors['monto'] = ['El monto debe ser mayor a 0.'];
        }
        
        if (!empty($errors)) {
            throw new WompiValidationException('Datos de enlace de pago inválidos.', $errors);
        }
    }

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
