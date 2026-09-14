<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MySQL/MariaDB gives the first NOT-NULL TIMESTAMP column in a table an
 * implicit `DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP()`
 * unless the server runs with explicit_defaults_for_timestamp=1 (it
 * doesn't, here). That silently reset exchanges.expires_at to "now" on
 * every UPDATE to the row — including ones that only touch, say,
 * max_file_size — instantly expiring the exchange from the admin panel.
 * email_verifications.expires_at and activity_logs.created_at have the
 * same latent bug.
 *
 * DATETIME columns never get this implicit behaviour, so every domain
 * timestamp here that isn't meant to track "row last touched" moves to
 * DATETIME. Casts stay 'datetime' either way — no model changes needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE exchanges MODIFY expires_at DATETIME NOT NULL');
        DB::statement('ALTER TABLE exchanges MODIFY disabled_at DATETIME NULL');
        DB::statement('ALTER TABLE exchanges MODIFY purged_at DATETIME NULL');

        DB::statement('ALTER TABLE email_verifications MODIFY expires_at DATETIME NOT NULL');
        DB::statement('ALTER TABLE email_verifications MODIFY consumed_at DATETIME NULL');

        DB::statement('ALTER TABLE activity_logs MODIFY created_at DATETIME NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE exchanges MODIFY expires_at TIMESTAMP NOT NULL');
        DB::statement('ALTER TABLE exchanges MODIFY disabled_at TIMESTAMP NULL');
        DB::statement('ALTER TABLE exchanges MODIFY purged_at TIMESTAMP NULL');

        DB::statement('ALTER TABLE email_verifications MODIFY expires_at TIMESTAMP NOT NULL');
        DB::statement('ALTER TABLE email_verifications MODIFY consumed_at TIMESTAMP NULL');

        DB::statement('ALTER TABLE activity_logs MODIFY created_at TIMESTAMP NOT NULL');
    }
};
