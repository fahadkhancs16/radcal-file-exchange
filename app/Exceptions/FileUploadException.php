<?php

namespace App\Exceptions;

use RuntimeException;

class FileUploadException extends RuntimeException
{
    public static function tooLarge(string $filename, int $limitBytes): self
    {
        $limitMb = round($limitBytes / 1024 / 1024);

        return new self("\"{$filename}\" is larger than this exchange's {$limitMb} MB limit.");
    }

    public static function empty(string $filename): self
    {
        return new self("\"{$filename}\" is empty and was not uploaded.");
    }
}
