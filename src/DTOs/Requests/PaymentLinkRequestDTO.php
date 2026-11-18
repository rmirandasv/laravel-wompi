<?php

namespace Rmirandasv\Wompi\DTOs\Requests;

use Rmirandasv\Wompi\DTOs\Requests\Concerns\ValidatesPayload;
use Rmirandasv\Wompi\Enums\CantidadCuotas;

/**
 * The request to create a payment link.
 *
 * @see https://docs.wompi.sv/metodos-api/enlace-de-pago
 */
class PaymentLinkRequestDTO
{
    use ValidatesPayload;

    /**
     * @param  float|null  $monto  The amount to show to the customer. When null the customer may type the amount.
     * @param  array<string, mixed>  $formaPago
     * @param  array<string, mixed>  $configuracion
     * @param  array<string, mixed>  $infoProducto
     * @param  array<string, mixed>  $vigencia
     * @param  array<string, mixed>  $limitesDeUso
     * @param  array<string, string>  $datosAdicionales
     * @param  array<string, mixed>  $extraData
     */
    public function __construct(
        public readonly ?float $monto = null,
        public readonly string $identificadorEnlaceComercio = '',
        public readonly string $nombreProducto = '',
        public readonly array $formaPago = [],
        public readonly array $configuracion = [],
        public readonly array $infoProducto = [],
        public readonly ?int $cantidadMaximaCuotas = null,
        public readonly array $vigencia = [],
        public readonly array $limitesDeUso = [],
        public readonly array $datosAdicionales = [],
        public readonly ?string $idGrupoTarjetas = null,
        public readonly ?string $idAplicativo = null,
        public readonly array $extraData = [],
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->monto !== null && $this->monto < 0.01) {
            $this->addError('monto', 'El monto debe ser mayor o igual a 0.01.');
        }

        if (! self::filled($this->identificadorEnlaceComercio)) {
            $this->addError('identificadorEnlaceComercio', 'El identificador del enlace es obligatorio.');
        } elseif (mb_strlen($this->identificadorEnlaceComercio) > 500) {
            $this->addError('identificadorEnlaceComercio', 'El identificador del enlace no puede exceder 500 caracteres.');
        }

        if (! self::filled($this->nombreProducto)) {
            $this->addError('nombreProducto', 'El nombre del producto es obligatorio.');
        } elseif (mb_strlen($this->nombreProducto) > 500) {
            $this->addError('nombreProducto', 'El nombre del producto no puede exceder 500 caracteres.');
        }

        if ($this->cantidadMaximaCuotas !== null && CantidadCuotas::tryFrom($this->cantidadMaximaCuotas) === null) {
            $this->addError('cantidadMaximaCuotas', 'La cantidad de cuotas no es válida.');
        }

        if (isset($this->configuracion['duracionInterfazIntentoMinutos'])) {
            $duration = $this->configuracion['duracionInterfazIntentoMinutos'];

            if (! is_numeric($duration) || (int) $duration < 10 || (int) $duration > 60) {
                $this->addError('configuracion.duracionInterfazIntentoMinutos', 'La duración debe estar entre 10 y 60 minutos.');
            }
        }

        if (isset($this->configuracion['cantidadPorDefecto']) && (! is_numeric($this->configuracion['cantidadPorDefecto']) || (int) $this->configuracion['cantidadPorDefecto'] < 1)) {
            $this->addError('configuracion.cantidadPorDefecto', 'La cantidad por defecto debe ser mayor o igual a 1.');
        }

        foreach (['cantidadMaximaPagosExitosos', 'cantidadMaximaPagosFallidos'] as $limit) {
            if (isset($this->limitesDeUso[$limit]) && (! is_numeric($this->limitesDeUso[$limit]) || (int) $this->limitesDeUso[$limit] < 0)) {
                $this->addError("limitesDeUso.{$limit}", 'El límite de uso no puede ser negativo.');
            }
        }

        $this->throwIfInvalid('Datos de enlace de pago inválidos.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            monto: self::floatFrom($data, 'monto'),
            identificadorEnlaceComercio: self::stringFrom($data, 'identificadorEnlaceComercio'),
            nombreProducto: self::stringFrom($data, 'nombreProducto'),
            formaPago: self::arrayFrom($data, 'formaPago'),
            configuracion: self::arrayFrom($data, 'configuracion'),
            infoProducto: self::arrayFrom($data, 'infoProducto'),
            cantidadMaximaCuotas: self::intFrom($data, 'cantidadMaximaCuotas'),
            vigencia: self::arrayFrom($data, 'vigencia'),
            limitesDeUso: self::arrayFrom($data, 'limitesDeUso'),
            datosAdicionales: self::arrayFrom($data, 'datosAdicionales'),
            idGrupoTarjetas: self::nullableStringFrom($data, 'idGrupoTarjetas'),
            idAplicativo: self::nullableStringFrom($data, 'idAplicativo'),
            extraData: self::except($data, [
                'monto',
                'identificadorEnlaceComercio',
                'nombreProducto',
                'formaPago',
                'configuracion',
                'infoProducto',
                'cantidadMaximaCuotas',
                'vigencia',
                'limitesDeUso',
                'datosAdicionales',
                'idGrupoTarjetas',
                'idAplicativo',
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = array_merge([
            'monto' => $this->monto,
            'identificadorEnlaceComercio' => $this->identificadorEnlaceComercio,
            'nombreProducto' => $this->nombreProducto,
            'formaPago' => $this->formaPago,
            'configuracion' => $this->configuracion,
            'infoProducto' => $this->infoProducto,
            'cantidadMaximaCuotas' => $this->cantidadMaximaCuotas,
            'vigencia' => $this->vigencia,
            'limitesDeUso' => $this->limitesDeUso,
            'datosAdicionales' => $this->datosAdicionales,
            'idGrupoTarjetas' => $this->idGrupoTarjetas,
            'idAplicativo' => $this->idAplicativo,
        ], $this->extraData);

        return self::withoutNulls($payload);
    }
}
