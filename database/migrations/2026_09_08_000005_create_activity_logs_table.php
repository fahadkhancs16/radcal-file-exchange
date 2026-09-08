<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Nullable + nullOnDelete so the trail survives an exchange being purged.
            $table->foreignId('exchange_id')->nullable()->constrained()->nullOnDelete();

            // Denormalised so a purged exchange is still identifiable in the log.
            $table->string('exchange_code', 64)->nullable()->index();

            $table->string('actor_type', 16); // App\Enums\ActorType
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label')->nullable(); // e.g. customer email, "Scheduler"

            $table->string('action', 48)->index(); // App\Enums\ActivityAction
            $table->json('meta')->nullable();       // filename, old/new expiry, ip, ...

            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->index(); // drives "most recent file activity"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
