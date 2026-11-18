<?php

namespace Rmirandasv\Wompi\Enums;

/**
 * The payment method used by a transaction.
 *
 * @see https://docs.wompi.sv/redirect-url/parametros-de-url-de-redirect
 */
enum FormaPago: string
{
    case PAGO_NORMAL = 'PagoNormal';
    case PUNTOS = 'Puntos';
    case CUOTAS = 'Cuotas';
    case BITCOIN = 'Bitcoin';
    case TARJETA_CREDITO_DEBITO = 'TarjetaCreditoDebito';
    case QUICK_PAY = 'QuickPay';
    case NEQUI = 'Nequi';

    /**
     * Resolve a value coming from the API, the webhook or a redirect URL.
     */
    public static function fromRaw(mixed $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof self) {
            return $value;
        }

        if (is_int($value)) {
            // The API also models the payment method as an integer
            // enumeration, but Wompi does not document the mapping.
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        return self::tryFrom($value);
    }
}
