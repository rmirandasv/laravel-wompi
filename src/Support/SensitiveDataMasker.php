<?php

namespace Rmirandasv\Wompi\Support;

/**
 * Removes cardholder data from arbitrary payloads before they reach a log.
 *
 * The masking is applied recursively over the whole payload tree, because the
 * Wompi API nests the card under "tarjetaCreditoDebido" and a naive root-level
 * mask would leak the PAN and the CVV into the application logs.
 *
 * In addition to matching known key names, values that *look* like a primary
 * account number (13 to 19 digits, spaces allowed) are masked even when they
 * are found under a key name we do not know about yet.
 */
final class SensitiveDataMasker
{
    public const MASKED = '***';

    public const REDACTED = '[REDACTED]';

    /**
     * Keys whose value is a primary account number.
     *
     * @var list<string>
     */
    private const PAN_KEYS = ['numerotarjeta'];

    /**
     * Keys whose value is a card verification value.
     *
     * @var list<string>
     */
    private const CVV_KEYS = ['cvv', 'codigoseguridad'];

    /**
     * Keys whose value is the expiry of a card.
     *
     * @var list<string>
     */
    private const EXPIRY_KEYS = ['mesvencimiento', 'aniovencimiento'];

    /**
     * Keys that hold a card object. Everything inside them is treated as
     * cardholder data even if the key name is unknown.
     *
     * @var list<string>
     */
    private const CARD_CONTAINER_KEYS = [
        'tarjeta',
        'tarjetacreditodebito',
        'tarjetacreditodebido',
        'datosTarjetatokenizada',
    ];

    /**
     * Mask every piece of sensitive data found in the given payload.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function mask(array $data): array
    {
        return self::walk($data, false);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function walk(array $data, bool $insideCard): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            $normalizedKey = self::normalizeKey((string) $key);

            if (in_array($normalizedKey, self::PAN_KEYS, true)) {
                $masked[$key] = self::maskPan(is_scalar($value) ? (string) $value : '');

                continue;
            }

            if (in_array($normalizedKey, self::CVV_KEYS, true)) {
                $masked[$key] = self::MASKED;

                continue;
            }

            if (in_array($normalizedKey, self::EXPIRY_KEYS, true)) {
                $masked[$key] = self::MASKED;

                continue;
            }

            if (is_array($value)) {
                $masked[$key] = self::walk(
                    $value,
                    $insideCard || in_array($normalizedKey, self::CARD_CONTAINER_KEYS, true)
                );

                continue;
            }

            if (is_string($value)) {
                if (self::looksLikePan($value)) {
                    $masked[$key] = self::maskPan($value);

                    continue;
                }

                if ($insideCard && self::looksLikeCvv($value)) {
                    $masked[$key] = self::MASKED;

                    continue;
                }
            }

            $masked[$key] = $value;
        }

        return $masked;
    }

    /**
     * Keep the first four and the last four digits, hide everything between.
     */
    private static function maskPan(string $pan): string
    {
        $digits = preg_replace('/[^0-9]/', '', $pan) ?? '';

        if (strlen($digits) < 8) {
            return self::REDACTED;
        }

        return substr($digits, 0, 4).str_repeat('*', max(8, strlen($digits) - 8)).substr($digits, -4);
    }

    /**
     * A primary account number is 13 to 19 digits long, optionally separated
     * by spaces.
     */
    private static function looksLikePan(string $value): bool
    {
        if (preg_match('/^[0-9][0-9 ]{11,22}[0-9]$/', $value) !== 1) {
            return false;
        }

        $digits = preg_replace('/[^0-9]/', '', $value) ?? '';

        return strlen($digits) >= 13 && strlen($digits) <= 19;
    }

    private static function looksLikeCvv(string $value): bool
    {
        return preg_match('/^[0-9]{3,4}$/', $value) === 1;
    }

    private static function normalizeKey(string $key): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $key) ?? $key);
    }
}
