<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_files', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exchange_id')->constrained()->cascadeOnDelete();

            $table->string('owner', 16); // App\Enums\FileOwner: radcal | customer

            // Shown to users. "Replace by same filename" is scoped to (exchange, owner).
            $table->string('original_filename');

            // Sanitised/hashed name actually written to disk. Generated server-side.
            $table->string('stored_name');

            $table->unsignedBigInteger('size');
            $table->string('mime')->nullable(); // sniffed, not trusted from the client

            // Staff member who uploaded it. Null when the customer uploaded.
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps(); // created_at = upload date

            // One name per side of the exchange -> same-name upload replaces.
            $table->unique(['exchange_id', 'owner', 'original_filename'], 'exchange_files_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_files');
    }
};
