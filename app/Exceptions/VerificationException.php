<?php

namespace App\Exceptions;

use RuntimeException;

class VerificationException extends RuntimeException
{
    public static function notFound(): self
    {
        return new self('We could not find a pending verification for that email. Please start again.');
    }

    public static function expired(): self
    {
        return new self('That verification code has expired. Request a new one.');
    }

    public static function locked(): self
    {
        return new self('Too many incorrect attempts. Request a new code.');
    }

    public static function mismatch(): self
    {
        return new self('That code is not correct.');
    }

    public static function tooSoon(int $seconds): self
    {
        return new self("Please wait {$seconds} seconds before requesting another code.");
    }
}
