<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_files', function (Blueprint $table) {
            // For owner = 'colleague': who the file was sent to. The sender is
            // uploaded_by_email.
            $table->string('recipient_email')->nullable()->after('uploaded_by_email');

            // Replaces the (exchange, owner, filename) unique key as the index
            // the exchange_id foreign key relies on. Colleague transfers need
            // the same filename per sender/recipient pair, which a wider unique
            // key can't express within MySQL's index-size limit — uniqueness is
            // enforced in FileService instead (as it already is for replace).
            $table->index(['exchange_id', 'owner'], 'exchange_files_exchange_owner_index');
        });

        Schema::table('exchange_files', function (Blueprint $table) {
            $table->dropUnique('exchange_files_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_files', function (Blueprint $table) {
            $table->unique(['exchange_id', 'owner', 'original_filename'], 'exchange_files_name_unique');
        });

        Schema::table('exchange_files', function (Blueprint $table) {
            $table->dropIndex('exchange_files_exchange_owner_index');
            $table->dropColumn('recipient_email');
        });
    }
};
