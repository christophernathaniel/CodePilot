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
        Schema::create('variable_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snippet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('values');
            $table->timestamps();

            $table->unique(['snippet_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variable_presets');
    }
};
