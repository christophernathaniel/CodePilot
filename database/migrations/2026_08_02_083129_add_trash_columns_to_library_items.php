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
        Schema::table('projects', function (Blueprint $table) {
            $table->softDeletes();
            $table->uuid('deletion_batch')->nullable()->index();
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->softDeletes();
            $table->uuid('deletion_batch')->nullable()->index();
        });

        Schema::table('snippets', function (Blueprint $table) {
            $table->softDeletes();
            $table->uuid('deletion_batch')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('snippets', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('deletion_batch');
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('deletion_batch');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('deletion_batch');
        });
    }
};
