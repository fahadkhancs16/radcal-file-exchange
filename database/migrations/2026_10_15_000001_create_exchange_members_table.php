<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Colleagues a customer invited into their exchange. Each has their own
        // password; they are never `users` rows.
        Schema::create('exchange_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exchange_id')->constrained()->cascadeOnDelete();

            $table->string('email');
            $table->string('name')->nullable();

            // Hash::make() — never plaintext.
            $table->string('password_hash');

            // Email of the customer-side person who sent the invite.
            $table->string('invited_by');

            $table->timestamps();

            $table->unique(['exchange_id', 'email']);
        });

        // Which customer-side person uploaded the file (owner/colleague email).
        Schema::table('exchange_files', function (Blueprint $table) {
            $table->string('uploaded_by_email')->nullable()->after('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_files', function (Blueprint $table) {
            $table->dropColumn('uploaded_by_email');
        });

        Schema::dropIfExists('exchange_members');
    }
};
