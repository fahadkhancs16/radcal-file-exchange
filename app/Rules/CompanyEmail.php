<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Only addresses on the configured company domain (iba-group.com) pass.
 * Matches the exact domain, case-insensitively — sub-domains and look-alikes
 * such as "iba-group.com.evil.test" are rejected.
 */
class CompanyEmail implements ValidationRule
{
    public static function domain(): string
    {
        return strtolower((string) config('exchange.allowed_email_domain', 'iba-group.com'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = strtolower(trim((string) $value));

        if (in_array($email, (array) config('exchange.allowed_emails', []), true)) {
            return;
        }

        $at = strrpos($email, '@');

        if ($at === false || substr($email, $at + 1) !== self::domain()) {
            $fail('Only @'.self::domain().' email addresses are allowed.');
        }
    }
}
