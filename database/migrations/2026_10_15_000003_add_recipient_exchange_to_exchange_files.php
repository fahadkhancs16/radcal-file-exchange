<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A colleague transfer is stored in the SENDER's exchange; this is the
        // exchange the recipient signs in to (their own, or the same one when
        // sending to an invited colleague).
        Schema::table('exchange_files', function (Blueprint $table) {
            $table->foreignId('recipient_exchange_id')->nullable()->after('recipient_email')
                ->constrained('exchanges')->nullOnDelete();
        });

        // Transfers made before this column existed were always within one exchange.
        DB::table('exchange_files')
            ->where('owner', 'colleague')
            ->update(['recipient_exchange_id' => DB::raw('exchange_id')]);
    }

    public function down(): void
    {
        Schema::table('exchange_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipient_exchange_id');
        });
    }
};
