<?php

namespace App\Support;

/**
 * Generates the password for a customer-initiated exchange. Guarantees at
 * least one uppercase letter, one lowercase letter, one digit and one
 * special character, drawn with the CSPRNG (random_int), then shuffled with
 * a Fisher–Yates pass — also CSPRNG-based, unlike PHP's shuffle().
 *
 * The alphabet skips visually-ambiguous characters (0/O, 1/l/I) since a
 * customer has to read this from an email and type it back, and skips the
 * backtick so the password can be safely wrapped in a Markdown code span
 * in the notification email without breaking it.
 */
final class PasswordGenerator
{
    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LOWER = 'abcdefghijkmnpqrstuvwxyz';

    private const DIGIT = '23456789';

    private const SYMBOL = '!@#$%&*+=?';

    public static function generate(?int $length = null): string
    {
        $length = max(8, $length ?? (int) config('exchange.password.length', 12));

        $classes = [self::UPPER, self::LOWER, self::DIGIT, self::SYMBOL];
        $all = implode('', $classes);

        // One guaranteed character per class, then fill the rest from the
        // full alphabet so class distribution isn't predictable beyond that.
        $chars = array_map(
            fn (string $set): string => $set[random_int(0, strlen($set) - 1)],
            $classes,
        );

        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
