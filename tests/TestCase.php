<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A stale bootstrap/cache/config.php (from running `php artisan
        // config:cache` locally) makes phpunit.xml's <env> overrides silently
        // ignored — RefreshDatabase would then wipe the real dev database.
        // Fail loudly instead.
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($database !== ':memory:' && ! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Refusing to run tests against database [{$database}]. "
                .'Run `php artisan config:clear` — config is cached and phpunit.xml is being ignored.'
            );
        }
    }
}
