<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('snippet_copy_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('snippet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('snippet_variation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variable_preset_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('method', ['keyboard', 'button']);
            $table->enum('representation', ['source', 'rendered']);
            $table->enum('scope', ['selection', 'full']);
            $table->unsignedInteger('selection_length')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['snippet_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snippet_copy_events');
    }
};
