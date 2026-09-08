<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchanges', function (Blueprint $table) {
            $table->id();

            // URL segment: share.radcal.com/{code}. Manual or auto-generated.
            $table->string('code', 64)->unique();

            // Hash::make() — never plaintext.
            $table->string('password_hash');

            $table->string('customer_name');
            $table->string('company')->nullable();
            $table->string('email')->index();
            $table->text('description')->nullable();

            $table->string('origin', 32); // App\Enums\ExchangeOrigin

            // Per-file ceiling in bytes. Default 100 MB (config('exchange.default_max_bytes')).
            $table->unsignedBigInteger('max_file_size');

            // Radcal staff member who created it. Null for customer-initiated.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // The actual date the exchange (folder + all files) is deleted.
            $table->timestamp('expires_at')->index();

            // Admin "disable" — blocks customer access without deleting anything.
            $table->timestamp('disabled_at')->nullable();

            // Set by the purge job; keeps a short audit tail after the files are gone.
            $table->timestamp('purged_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchanges');
    }
};
