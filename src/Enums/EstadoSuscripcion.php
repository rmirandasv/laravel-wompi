<?php

namespace Rmirandasv\Wompi\Enums;

/**
 * The state of a subscription attached to a recurring payment link.
 *
 * As with ResultadoTransaccion, Wompi documents an integer enumeration but
 * does not publish the meaning of each value, so the raw value is preserved.
 *
 * @see https://api.wompi.sv/metodos-api/crear-enlace-pago-recurrentes
 */
enum EstadoSuscripcion: int
{
    case SIN_ESPECIFICAR = 0;
    case PENDIENTE = 1;
    case ACTIVA = 2;
    case PAUSADA = 3;
    case CANCELADA = 4;

    /**
     * The raw value as sent by Wompi.
     */
    public function raw(): int
    {
        return $this->value;
    }

    /**
     * A human readable label.
     *
     * Wompi does not document the mapping between the integer values and their
     * meaning, so the generic label is returned until the mapping is
     * confirmed by Wompi.
     */
    public function label(): string
    {
        return match ($this) {
            self::SIN_ESPECIFICAR => 'Sin especificar',
            self::PENDIENTE => 'Pendiente',
            self::ACTIVA => 'Activa',
            self::PAUSADA => 'Pausada',
            self::CANCELADA => 'Cancelada',
        };
    }

    /**
     * Build the enum from the raw value, tolerating unknown values.
     */
    public static function fromRaw(int|string|null $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (self::tryFrom((int) $value) ?? self::SIN_ESPECIFICAR) : null;
    }
}
