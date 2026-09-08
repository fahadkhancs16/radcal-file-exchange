<?php

namespace App\Support;

use App\Models\Exchange;
use InvalidArgumentException;

/**
 * Exchange codes: the public URL segment (share.radcal.com/{code}).
 * Generated codes use an unambiguous alphabet; manual codes are validated
 * to the same character set so every code is URL- and phone-friendly.
 */
final class ExchangeCode
{
    /** Generate a unique code not currently used by any exchange. */
    public static function generate(): string
    {
        $alphabet = (string) config('exchange.code_alphabet');
        $length = (int) config('exchange.code_length');
        $max = strlen($alphabet) - 1;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, $max)];
            }
        } while (Exchange::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Normalise and validate an admin-entered code.
     *
     * @throws InvalidArgumentException
     */
    public static function normalise(string $code): string
    {
        $code = strtoupper(trim($code));

        if (! preg_match('/^[A-Z0-9-]{3,64}$/', $code)) {
            throw new InvalidArgumentException(
                'An exchange code must be 3–64 characters using letters, digits and hyphens.'
            );
        }

        return $code;
    }

    public static function isAvailable(string $code): bool
    {
        return ! Exchange::query()->where('code', $code)->exists();
    }
}
