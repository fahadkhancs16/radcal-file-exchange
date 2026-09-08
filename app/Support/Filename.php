<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Filename handling. The customer's original string is kept in the database
 * for display and for "replace by same filename", but it never touches a
 * filesystem path — the name on disk is generated here.
 */
final class Filename
{
    /**
     * Clean a user-supplied filename for storage in original_filename:
     * strip any path, collapse whitespace, drop control characters, cap length.
     */
    public static function clean(string $name): string
    {
        // Defeat "../" and "C:\..." — keep only the basename.
        $name = str_replace('\\', '/', $name);
        $name = basename($name);

        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        $name = trim($name, " .\t\n\r\0\x0B");

        if ($name === '') {
            $name = 'file';
        }

        return Str::limit($name, 200, '');
    }

    /**
     * The key used to decide whether an upload replaces an existing file:
     * trimmed, lower-cased basename. (The DB collation is also case-insensitive;
     * this keeps the comparison explicit and portable.)
     */
    public static function comparisonKey(string $name): string
    {
        return Str::lower(self::clean($name));
    }

    /**
     * A collision-free name to write to disk: <uuid>.<sanitised-ext>.
     */
    public static function storedName(string $originalName): string
    {
        $ext = Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?? '';

        return $ext === ''
            ? (string) Str::uuid()
            : Str::uuid().'.'.Str::limit($ext, 12, '');
    }
}
