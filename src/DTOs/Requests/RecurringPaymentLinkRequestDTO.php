<?php

namespace Rmirandasv\Wompi\DTOs\Requests;

use Rmirandasv\Wompi\Exceptions\WompiValidationException;

class RecurringPaymentLinkRequestDTO
{
    public function __construct(
        public readonly int $diaDePago,
        public readonly string $nombre,
        public readonly string $idAplicativo,
        public readonly float $monto,
        public readonly string $descripcionProducto,
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
        
        if ($this->diaDePago < 1 || $this->diaDePago > 31) {
            $errors['diaDePago'] = ['El día de pago debe estar entre 1 y 31.'];
        }

        if (empty(trim($this->nombre))) {
            $errors['nombre'] = ['El nombre es requerido.'];
        }

        if (empty(trim($this->idAplicativo))) {
            $errors['idAplicativo'] = ['El idAplicativo es requerido.'];
        }

        if (empty(trim($this->descripcionProducto))) {
            $errors['descripcionProducto'] = ['La descripción del producto es requerida.'];
        }
        
        if (!empty($errors)) {
            throw new WompiValidationException('Datos de enlace de pago recurrente inválidos.', $errors);
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['diaDePago'] ?? 0,
            $data['nombre'] ?? '',
            $data['idAplicativo'] ?? '',
            $data['monto'] ?? 0.0,
            $data['descripcionProducto'] ?? '',
            array_diff_key($data, array_flip(['diaDePago', 'nombre', 'idAplicativo', 'monto', 'descripcionProducto']))
        );
    }

    public function toArray(): array
    {
        return array_merge([
            'diaDePago' => $this->diaDePago,
            'nombre' => $this->nombre,
            'idAplicativo' => $this->idAplicativo,
            'monto' => $this->monto,
            'descripcionProducto' => $this->descripcionProducto,
        ], $this->extraData);
    }
}
