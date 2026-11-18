<?php

namespace Rmirandasv\Wompi\DTOs\Requests;

use Rmirandasv\Wompi\DTOs\Requests\Concerns\ValidatesPayload;

/**
 * The request to charge a tokenized card without 3D Secure.
 *
 * This is the endpoint used to bill a subscription that was already
 * tokenized, as opposed to RecurringPaymentLinkRequestDTO which creates a
 * link the customer affiliates themselves to.
 *
 * @see https://api.wompi.sv/index.html
 */
class TokenizedTransactionRequestDTO
{
    use ValidatesPayload;

    /**
     * @param  array<string, mixed>  $configuracion
     * @param  array<string, string>  $datosAdicionales
     */
    public function __construct(
        public readonly float $monto,
        public readonly string $tokenTarjeta = '',
        public readonly string $emailCliente = '',
        public readonly string $nombreCliente = '',
        public readonly array $configuracion = [],
        public readonly array $datosAdicionales = [],
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->monto < 0.01) {
            $this->addError('monto', 'El monto debe ser mayor o igual a 0.01.');
        }

        if (! self::filled($this->tokenTarjeta)) {
            $this->addError('tokenTarjeta', 'El token de la tarjeta es obligatorio.');
        }

        if (! self::filled($this->nombreCliente)) {
            $this->addError('nombreCliente', 'El nombre del cliente es obligatorio.');
        }

        if (! self::filled($this->emailCliente)) {
            $this->addError('emailCliente', 'El email del cliente es obligatorio.');
        } elseif (filter_var($this->emailCliente, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError('emailCliente', 'El email del cliente no es válido.');
        }

        $this->throwIfInvalid('Datos de transacción tokenizada inválidos.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            monto: self::floatFrom($data, 'monto') ?? 0.0,
            tokenTarjeta: self::stringFrom($data, 'tokenTarjeta'),
            emailCliente: self::stringFrom($data, 'emailCliente'),
            nombreCliente: self::stringFrom($data, 'nombreCliente'),
            configuracion: self::arrayFrom($data, 'configuracion'),
            datosAdicionales: self::arrayFrom($data, 'datosAdicionales'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return self::withoutNulls([
            'monto' => $this->monto,
            'tokenTarjeta' => $this->tokenTarjeta,
            'emailCliente' => $this->emailCliente,
            'nombreCliente' => $this->nombreCliente,
            'configuracion' => $this->configuracion,
            'datosAdicionales' => $this->datosAdicionales,
        ]);
    }
}
