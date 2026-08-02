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
        Schema::create('snippets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('filename');
            $table->string('language', 50)->default('plaintext');
            $table->text('description')->nullable();
            $table->longText('content');
            $table->unsignedInteger('current_version')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'folder_id', 'filename']);
            $table->index(['project_id', 'folder_id', 'position']);
            $table->index(['project_id', 'language']);
            $table->index(['project_id', 'last_opened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snippets');
    }
};
