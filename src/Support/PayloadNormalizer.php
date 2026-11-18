<?php

namespace Rmirandasv\Wompi\Support;

use Rmirandasv\Wompi\Webhooks\WompiWebhook;

/**
 * Normalizes a Wompi payload into a canonical camelCase shape.
 *
 * Wompi is not consistent about the casing of its payloads:
 *
 *   - The webhook body uses PascalCase ("IdTransaccion", "ResultadoTransaccion",
 *     "Monto", "EsProductiva", "EnlacePago" ...).
 *   - The API responses use camelCase ("idTransaccion", "resultadoTransaccion",
 *     "monto", "esAprobada", "enlacePago" ...).
 *
 * Every lookup is therefore case insensitive, with an exact match taking
 * precedence over a case insensitive one. This makes the package work with
 * both shapes without the application having to know which one it received.
 */
final class PayloadNormalizer
{
    /**
     * Build the canonical representation of a webhook payload.
     *
     * @param  array<array-key, mixed>  $payload
     */
    public static function normalize(array $payload): WompiWebhook
    {
        $paymentLink = self::pickArray($payload, 'EnlacePago', 'enlacePago');

        $canonical = [
            'idTransaccion' => self::pickString($payload, 'IdTransaccion', 'idTransaccion'),
            'monto' => self::pickScalar($payload, 'Monto', 'monto'),
            'resultadoTransaccion' => self::pickScalar($payload, 'ResultadoTransaccion', 'resultadoTransaccion'),
            'esAprobada' => self::pickBool($payload, 'EsAprobada', 'esAprobada'),
            'esProductiva' => self::pickBool($payload, 'EsProductiva', 'esProductiva', 'EsReal', 'esReal'),
            'formaPagoUtilizada' => self::pickString(
                $payload,
                'FormaPagoUtilizada',
                'formaPagoUtilizada',
                'FormaPago',
                'formaPago'
            ),
            'moduloUtilizado' => self::pickString($payload, 'ModuloUtilizado', 'moduloUtilizado'),
            'codigoAutorizacion' => self::pickString($payload, 'CodigoAutorizacion', 'codigoAutorizacion'),
            'idIntentoPago' => self::pickString($payload, 'IdIntentoPago', 'idIntentoPago'),
            'cantidad' => self::pickInt($payload, 'Cantidad', 'cantidad'),
            'fechaTransaccion' => self::pickString($payload, 'FechaTransaccion', 'fechaTransaccion'),
            'idCuenta' => self::pickString($payload, 'IdCuenta', 'idCuenta'),
            'datosAdicionales' => self::pickArray($payload, 'DatosAdicionales', 'datosAdicionales'),
            'identificadorEnlaceComercio' => self::pickString(
                $paymentLink,
                'IdentificadorEnlaceComercio',
                'identificadorEnlaceComercio'
            ),
            'idEnlace' => self::pickScalar($paymentLink, 'IdEnlace', 'idEnlace', 'Id', 'id'),
            'nombreProducto' => self::pickString($paymentLink, 'NombreProducto', 'nombreProducto'),
            'cliente' => self::pickArray($payload, 'cliente', 'Cliente'),
            'aplicativo' => self::pickArray($payload, 'Aplicativo', 'aplicativo'),
        ];

        return new WompiWebhook($payload, $canonical);
    }

    /**
     * Find the first key that exists, preferring an exact match over a case
     * insensitive one.
     *
     * @param  array<array-key, mixed>  $source
     */
    public static function pick(array $source, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                return $source[$key];
            }
        }

        foreach ($keys as $key) {
            foreach ($source as $candidate => $value) {
                if (strcasecmp((string) $candidate, $key) === 0) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $source
     * @return array<array-key, mixed>
     */
    public static function pickArray(array $source, string ...$keys): array
    {
        $value = self::pick($source, ...$keys);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<array-key, mixed>  $source
     */
    public static function pickString(array $source, string ...$keys): ?string
    {
        $value = self::pick($source, ...$keys);

        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * @param  array<array-key, mixed>  $source
     */
    public static function pickScalar(array $source, string ...$keys): mixed
    {
        $value = self::pick($source, ...$keys);

        if (is_array($value) || is_object($value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $source
     */
    public static function pickInt(array $source, string ...$keys): ?int
    {
        $value = self::pickScalar($source, ...$keys);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $source
     */
    public static function pickBool(array $source, string ...$keys): ?bool
    {
        return self::toBool(self::pickScalar($source, ...$keys));
    }

    /**
     * Coerce the different boolean representations Wompi uses.
     *
     * The redirect URL sends "true"/"True"/"1", the webhook sends a real
     * boolean and the API responses send a real boolean as well.
     */
    public static function toBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }

        if (! is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            'true', '1', 'yes' => true,
            'false', '0', 'no' => false,
            default => null,
        };
    }
}
