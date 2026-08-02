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
        Schema::table('snippets', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->string('location_key')->nullable()->after('folder_id');
        });

        DB::table('snippets')->update([
            'user_id' => DB::raw('(SELECT user_id FROM projects WHERE projects.id = snippets.project_id)'),
        ]);

        DB::table('snippets')
            ->select(['id', 'project_id', 'folder_id'])
            ->orderBy('id')
            ->each(function (object $snippet): void {
                $locationKey = $snippet->folder_id !== null
                    ? 'folder:'.$snippet->folder_id
                    : 'project:'.$snippet->project_id;

                DB::table('snippets')
                    ->where('id', $snippet->id)
                    ->update(['location_key' => $locationKey]);
            });

        Schema::table('snippets', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->string('location_key')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable()->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();

            $table->dropUnique(['project_id', 'folder_id', 'filename']);
            $table->unique(['user_id', 'location_key', 'filename']);
            $table->index(['user_id', 'language']);
            $table->index(['user_id', 'location_key', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('snippets', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'language']);
            $table->dropIndex(['user_id', 'location_key', 'position']);
            $table->dropUnique(['user_id', 'location_key', 'filename']);
            $table->dropForeign(['project_id']);
            $table->dropForeign(['user_id']);
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique(['project_id', 'folder_id', 'filename']);
            $table->dropColumn(['user_id', 'location_key']);
        });
    }
};
