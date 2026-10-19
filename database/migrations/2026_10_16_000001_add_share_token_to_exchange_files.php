<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Public share links: every customer-uploaded file gets an unguessable token
 * that backs /share/{token}.
 *
 * This migration also removes the schema left behind by the abandoned
 * "colleague / team" feature (migrations 2026_10_15_000001-3, now deleted)
 * on any database that already ran them. Each step is guarded, so on a
 * database that never had that feature it does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->removeColleagueFeature();

        Schema::table('exchange_files', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('mime');
        });

        DB::table('exchange_files')
            ->where('owner', 'customer')
            ->whereNull('share_token')
            ->orderBy('id')
            ->each(fn ($row) => DB::table('exchange_files')
                ->where('id', $row->id)
                ->update(['share_token' => Str::random(40)]));
    }

    public function down(): void
    {
        Schema::table('exchange_files', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }

    private function removeColleagueFeature(): void
    {
        Schema::dropIfExists('exchange_members');

        // Colleague transfers were a third file owner; the old unique key
        // (exchange, owner, filename) cannot hold with them still present.
        if (Schema::hasColumn('exchange_files', 'recipient_exchange_id')) {
            Schema::table('exchange_files', fn (Blueprint $t) => $t->dropConstrainedForeignId('recipient_exchange_id'));
        }

        DB::table('exchange_files')->where('owner', 'colleague')->delete();

        foreach (['recipient_email', 'uploaded_by_email'] as $column) {
            if (Schema::hasColumn('exchange_files', $column)) {
                Schema::table('exchange_files', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }

        if (! Schema::hasIndex('exchange_files', 'exchange_files_name_unique')) {
            Schema::table('exchange_files', fn (Blueprint $t) => $t->unique(['exchange_id', 'owner', 'original_filename'], 'exchange_files_name_unique'));
        }

        if (Schema::hasIndex('exchange_files', 'exchange_files_exchange_owner_index')) {
            Schema::table('exchange_files', fn (Blueprint $t) => $t->dropIndex('exchange_files_exchange_owner_index'));
        }
    }
};
