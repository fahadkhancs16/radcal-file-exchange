<?php

namespace App\Exceptions;

use RuntimeException;

class ColleagueException extends RuntimeException
{
    public static function isOwner(): self
    {
        return new self('That address is already the contact for this exchange.');
    }

    public static function alreadyInvited(string $email): self
    {
        return new self("{$email} already has access to this exchange.");
    }

    public static function limitReached(int $limit): self
    {
        return new self("An exchange can have at most {$limit} invited colleagues.");
    }
}
