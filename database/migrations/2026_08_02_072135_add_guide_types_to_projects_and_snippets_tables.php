<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('kind', ['project', 'bundle', 'guide'])
                ->default('project')
                ->change();
        });

        Schema::table('snippets', function (Blueprint $table) {
            $table->string('content_type', 20)
                ->default('snippet')
                ->after('description');
            $table->index(['user_id', 'content_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('projects')
            ->where('kind', 'guide')
            ->update(['kind' => 'project']);

        Schema::table('projects', function (Blueprint $table) {
            $table->enum('kind', ['project', 'bundle'])
                ->default('project')
                ->change();
        });

        Schema::table('snippets', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'content_type']);
            $table->dropColumn('content_type');
        });
    }
};
