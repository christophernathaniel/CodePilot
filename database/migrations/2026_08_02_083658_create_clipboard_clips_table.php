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
        Schema::create('clipboard_clips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clipboard_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('snippet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('snippet_variation_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('content');
            $table->string('language', 50);
            $table->string('representation', 20);
            $table->string('source_title');
            $table->string('source_filename');
            $table->string('source_project')->nullable();
            $table->json('source_folders');
            $table->string('source_variation', 100);
            $table->unsignedInteger('line_start');
            $table->unsignedInteger('line_end');
            $table->timestamps();

            $table->index(['clipboard_session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clipboard_clips');
    }
};
