<?php

namespace Rmirandasv\Wompi\DTOs\Requests;

use Rmirandasv\Wompi\DTOs\Requests\Concerns\ValidatesPayload;

/**
 * The request to create a recurring payment link, the object Wompi uses to
 * sell a monthly subscription.
 *
 * The customer affiliates themselves through the returned urlEnlace, and
 * Wompi charges the subscription every month.
 *
 * @see https://docs.wompi.sv/metodos-api/crear-enlace-pago-recurrentes
 */
class RecurringPaymentLinkRequestDTO
{
    use ValidatesPayload;

    /**
     * @param  int  $diaDePago  The day of the month the subscription is charged, from 1 to 31.
     */
    public function __construct(
        public readonly int $diaDePago,
        public readonly string $nombre,
        public readonly float $monto,
        public readonly ?string $idAplicativo = null,
        public readonly ?string $descripcionProducto = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->diaDePago < 1 || $this->diaDePago > 31) {
            $this->addError('diaDePago', 'El día de pago debe estar entre 1 y 31.');
        }

        if (! self::filled($this->nombre)) {
            $this->addError('nombre', 'El nombre de la suscripción es obligatorio.');
        } elseif (mb_strlen($this->nombre) > 200) {
            $this->addError('nombre', 'El nombre de la suscripción no puede exceder 200 caracteres.');
        }

        if ($this->monto < 0.01) {
            $this->addError('monto', 'El monto debe ser mayor o igual a 0.01.');
        }

        if ($this->descripcionProducto !== null && mb_strlen($this->descripcionProducto) > 3000) {
            $this->addError('descripcionProducto', 'La descripción del producto no puede exceder 3000 caracteres.');
        }

        $this->throwIfInvalid('Datos de enlace de pago recurrente inválidos.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            diaDePago: self::intFrom($data, 'diaDePago') ?? 0,
            nombre: self::stringFrom($data, 'nombre'),
            monto: self::floatFrom($data, 'monto') ?? 0.0,
            idAplicativo: self::nullableStringFrom($data, 'idAplicativo'),
            descripcionProducto: self::nullableStringFrom($data, 'descripcionProducto'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return self::withoutNulls([
            'diaDePago' => $this->diaDePago,
            'nombre' => $this->nombre,
            'monto' => $this->monto,
            'idAplicativo' => $this->idAplicativo,
            'descripcionProducto' => $this->descripcionProducto,
        ]);
    }
}
