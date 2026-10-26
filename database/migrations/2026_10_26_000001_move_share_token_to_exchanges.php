<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One public share link per exchange instead of one per file.
 *
 * `exchanges.share_token` backs /share/{token}, a password-less page listing
 * every file the customer uploaded. The per-file `exchange_files.share_token`
 * is dropped, so links that were already emailed for a single file stop working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchanges', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('code');
        });

        DB::table('exchanges')
            ->whereNull('share_token')
            ->orderBy('id')
            ->each(fn ($row) => DB::table('exchanges')
                ->where('id', $row->id)
                ->update(['share_token' => Str::random(40)]));

        if (Schema::hasColumn('exchange_files', 'share_token')) {
            Schema::table('exchange_files', function (Blueprint $table) {
                $table->dropUnique(['share_token']);
                $table->dropColumn('share_token');
            });
        }
    }

    public function down(): void
    {
        Schema::table('exchange_files', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('mime');
        });

        DB::table('exchange_files')
            ->where('owner', 'customer')
            ->orderBy('id')
            ->each(fn ($row) => DB::table('exchange_files')
                ->where('id', $row->id)
                ->update(['share_token' => Str::random(40)]));

        Schema::table('exchanges', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
