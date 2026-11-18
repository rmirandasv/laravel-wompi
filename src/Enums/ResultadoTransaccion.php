<?php

namespace Rmirandasv\Wompi\Enums;

/**
 * The result of a purchase transaction as reported by the API.
 *
 * Wompi documents this field as an integer enumeration but does not publish
 * the meaning of each value, so the package does not guess: the raw value is
 * preserved and the boolean "esAprobada" field is used as the source of truth
 * when deciding whether a transaction was approved.
 *
 * @see https://api.wompi.sv/index.html
 */
enum ResultadoTransaccion: int
{
    case SIN_ESPECIFICAR = 0;
    case APROBADA = 1;
    case RECHAZADA = 2;

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
     * confirmed. Use WompiTransactionDetailDTO::$esAprobada to decide
     * whether a transaction was approved.
     */
    public function label(): string
    {
        return match ($this) {
            self::SIN_ESPECIFICAR => 'Sin especificar',
            self::APROBADA => 'Aprobada',
            self::RECHAZADA => 'Rechazada',
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
