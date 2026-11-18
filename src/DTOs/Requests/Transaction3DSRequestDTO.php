<?php

namespace Rmirandasv\Wompi\DTOs\Requests;

use Rmirandasv\Wompi\DTOs\Requests\Concerns\ValidatesPayload;

/**
 * The request to create a 3DS purchase transaction.
 *
 * Wompi expects the card inside a "tarjetaCreditoDebido" object and requires
 * the customer information (name, email, address, country and region).
 *
 * @see https://docs.wompi.sv/metodos-api/crear-transaccion-compra-3ds
 */
class Transaction3DSRequestDTO
{
    use ValidatesPayload;

    /**
     * @param  array<string, mixed>  $tarjetaCreditoDebido  numeroTarjeta, cvv, mesVencimiento, anioVencimiento, nombreEnTarjeta
     * @param  array<string, mixed>  $configuracion
     * @param  array<string, string>  $datosAdicionales
     */
    public function __construct(
        public readonly float $monto,
        public readonly array $tarjetaCreditoDebido = [],
        public readonly string $urlRedirect = '',
        public readonly string $nombre = '',
        public readonly string $apellido = '',
        public readonly string $email = '',
        public readonly string $ciudad = '',
        public readonly string $direccion = '',
        public readonly string $idPais = '',
        public readonly string $idRegion = '',
        public readonly string $codigoPostal = '',
        public readonly string $telefono = '',
        public readonly array $configuracion = [],
        public readonly array $datosAdicionales = [],
        public readonly ?string $idExterno = null,
        public readonly ?string $idGrupoTarjetas = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->monto < 0.01) {
            $this->addError('monto', 'El monto debe ser mayor o igual a 0.01.');
        }

        $card = $this->tarjetaCreditoDebido;

        if (! is_array($card) || $card === []) {
            $this->addError('tarjetaCreditoDebido', 'Los datos de la tarjeta son obligatorios.');
        } else {
            $numero = $card['numeroTarjeta'] ?? null;

            if (! self::filled($numero)) {
                $this->addError('tarjetaCreditoDebido.numeroTarjeta', 'El número de tarjeta es obligatorio.');
            } elseif (preg_match('/^[^3][ 0-9]{10,20}$/', (string) $numero) !== 1) {
                $this->addError('tarjetaCreditoDebido.numeroTarjeta', 'El número de tarjeta no tiene un formato válido.');
            }

            $cvv = $card['cvv'] ?? null;

            if (! self::filled($cvv)) {
                $this->addError('tarjetaCreditoDebido.cvv', 'El CVV es obligatorio.');
            } elseif (preg_match('/^[0-9]+$/', (string) $cvv) !== 1 || mb_strlen((string) $cvv) > 3) {
                $this->addError('tarjetaCreditoDebido.cvv', 'El CVV debe contener hasta 3 dígitos.');
            }

            $month = $card['mesVencimiento'] ?? null;

            if (! is_numeric($month) || (int) $month < 1 || (int) $month > 12) {
                $this->addError('tarjetaCreditoDebido.mesVencimiento', 'El mes de vencimiento debe estar entre 1 y 12.');
            }

            $year = $card['anioVencimiento'] ?? null;

            if (! is_numeric($year) || (int) $year < 2000 || (int) $year > 2100) {
                $this->addError('tarjetaCredito Debido.anioVencimiento', 'El año de vencimiento no es válido.');
            }
        }

        $required = [
            'urlRedirect' => 'La URL de redirect es obligatoria.',
            'nombre' => 'El nombre del cliente es obligatorio.',
            'apellido' => 'El apellido del cliente es obligatorio.',
            'email' => 'El email del cliente es obligatorio.',
            'ciudad' => 'La ciudad del cliente es obligatoria.',
            'direccion' => 'La dirección del cliente es obligatoria.',
            'idPais' => 'El país del cliente es obligatorio.',
            'idRegion' => 'La región del cliente es obligatoria.',
            'codigoPostal' => 'El código postal del cliente es obligatorio.',
            'telefono' => 'El teléfono del cliente es obligatorio.',
        ];

        foreach ($required as $field => $message) {
            if (! self::filled($this->{$field})) {
                $this->addError($field, $message);
            }
        }

        if (self::filled($this->email) && filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError('email', 'El email del cliente no es válido.');
        }

        if (self::filled($this->telefono) && preg_match('/^[0-9]+$/', $this->telefono) !== 1) {
            $this->addError('telefono', 'El teléfono del cliente solo puede contener dígitos.');
        }

        $this->throwIfInvalid('Datos de transacción 3DS inválidos.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $card = self::arrayFrom($data, 'tarjetaCreditoDebido');

        return new self(
            monto: self::floatFrom($data, 'monto') ?? 0.0,
            tarjetaCreditoDebido: $card,
            urlRedirect: self::stringFrom($data, 'urlRedirect'),
            nombre: self::stringFrom($data, 'nombre'),
            apellido: self::stringFrom($data, 'apellido'),
            email: self::stringFrom($data, 'email'),
            ciudad: self::stringFrom($data, 'ciudad'),
            direccion: self::stringFrom($data, 'direccion'),
            idPais: self::stringFrom($data, 'idPais'),
            idRegion: self::stringFrom($data, 'idRegion'),
            codigoPostal: self::stringFrom($data, 'codigoPostal'),
            telefono: self::stringFrom($data, 'telefono'),
            configuracion: self::arrayFrom($data, 'configuracion'),
            datosAdicionales: self::arrayFrom($data, 'datosAdicionales'),
            idExterno: self::nullableStringFrom($data, 'idExterno'),
            idGrupoTarjetas: self::nullableStringFrom($data, 'idGrupoTarjetas'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return self::withoutNulls([
            'monto' => $this->monto,
            'tarjetaCreditoDebido' => $this->tarjetaCreditoDebido,
            'urlRedirect' => $this->urlRedirect,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'email' => $this->email,
            'ciudad' => $this->ciudad,
            'direccion' => $this->direccion,
            'idPais' => $this->idPais,
            'idRegion' => $this->idRegion,
            'codigoPostal' => $this->codigoPostal,
            'telefono' => $this->telefono,
            'configuracion' => $this->configuracion,
            'datosAdicionales' => $this->datosAdicionales,
            'idExterno' => $this->idExterno,
            'idGrupoTarjetas' => $this->idGrupoTarjetas,
        ]);
    }
}
