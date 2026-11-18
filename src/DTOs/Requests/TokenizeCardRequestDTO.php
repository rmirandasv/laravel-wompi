<?php

namespace Rmirandasv\Wompi\DTOs\Requests;

use Rmirandasv\Wompi\DTOs\Requests\Concerns\ValidatesPayload;

/**
 * The request to tokenize a card.
 *
 * Wompi expects "mesVencimiento" and "anioVencimiento" (integers), not the
 * "mesExpiracion" / "anioExpiracion" names used by earlier versions of this
 * package.
 *
 * @see https://docs.wompi.sv/metodos-api/tokenizacion
 */
class TokenizeCardRequestDTO
{
    use ValidatesPayload;

    /**
     * @param  int  $mesVencimiento  The expiry month, from 1 to 12.
     * @param  int  $anioVencimiento  The expiry year, as four digits.
     */
    public function __construct(
        public readonly string $numeroTarjeta = '',
        public readonly string $cvv = '',
        public readonly int $mesVencimiento = 0,
        public readonly int $anioVencimiento = 0,
        public readonly ?string $nombreEnTarjeta = null,
        public readonly ?string $idGrupoTarjetas = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (! self::filled($this->numeroTarjeta)) {
            $this->addError('numeroTarjeta', 'El número de tarjeta es obligatorio.');
        } elseif (preg_match('/^[^3][ 0-9]{10,20}$/', $this->numeroTarjeta) !== 1) {
            $this->addError('numeroTarjeta', 'El número de tarjeta no tiene un formato válido.');
        }

        if (! self::filled($this->cvv)) {
            $this->addError('cvv', 'El CVV es obligatorio.');
        } elseif (preg_match('/^[0-9]+$/', $this->cvv) !== 1 || mb_strlen($this->cvv) > 3) {
            $this->addError('cvv', 'El CVV debe contener hasta 3 dígitos.');
        }

        if ($this->mesVencimiento < 1 || $this->mesVencimiento > 12) {
            $this->addError('mesVencimiento', 'El mes de vencimiento debe estar entre 1 y 12.');
        }

        if ($this->anioVencimiento < 2000 || $this->anioVencimiento > 2100) {
            $this->addError('anioVencimiento', 'El año de vencimiento no es válido.');
        }

        if ($this->idGrupoTarjetas !== null && mb_strlen($this->idGrupoTarjetas) > 50) {
            $this->addError('idGrupoTarjetas', 'El grupo de tarjetas no puede exceder 50 caracteres.');
        }

        $this->throwIfInvalid('Datos de tokenización de tarjeta inválidos.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            numeroTarjeta: self::stringFrom($data, 'numeroTarjeta'),
            cvv: self::stringFrom($data, 'cvv'),
            mesVencimiento: self::intFrom($data, 'mesVencimiento') ?? 0,
            anioVencimiento: self::intFrom($data, 'anioVencimiento') ?? 0,
            nombreEnTarjeta: self::nullableStringFrom($data, 'nombreEnTarjeta'),
            idGrupoTarjetas: self::nullableStringFrom($data, 'idGrupoTarjetas'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return self::withoutNulls([
            'numeroTarjeta' => $this->numeroTarjeta,
            'cvv' => $this->cvv,
            'mesVencimiento' => $this->mesVencimiento,
            'anioVencimiento' => $this->anioVencimiento,
            'nombreEnTarjeta' => $this->nombreEnTarjeta,
            'idGrupoTarjetas' => $this->idGrupoTarjetas,
        ]);
    }
}
