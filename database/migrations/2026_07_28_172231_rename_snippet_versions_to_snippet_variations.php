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
        Schema::rename('snippet_versions', 'snippet_variations');

        Schema::table('snippet_variations', function (Blueprint $table) {
            $table->renameColumn('number', 'position');
            $table->renameColumn('label', 'name');
        });

        Schema::table('snippet_variations', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('position');
        });

        $snippets = DB::table('snippets')
            ->select(['id', 'content', 'current_version'])
            ->orderBy('id')
            ->get();

        foreach ($snippets as $snippet) {
            DB::table('snippet_variations')
                ->where('snippet_id', $snippet->id)
                ->whereNull('name')
                ->orderBy('position')
                ->get(['id', 'position'])
                ->each(function (object $variation): void {
                    DB::table('snippet_variations')
                        ->where('id', $variation->id)
                        ->update(['name' => "Variation {$variation->position} ({$variation->id})"]);
                });

            $defaultVariationId = DB::table('snippet_variations')
                ->where('snippet_id', $snippet->id)
                ->where('position', $snippet->current_version)
                ->value('id');

            $defaultVariationId ??= DB::table('snippet_variations')
                ->where('snippet_id', $snippet->id)
                ->where('content', $snippet->content)
                ->orderBy('position')
                ->value('id');

            $defaultVariationId ??= DB::table('snippet_variations')
                ->where('snippet_id', $snippet->id)
                ->orderBy('position')
                ->value('id');

            if ($defaultVariationId === null) {
                $timestamp = now();
                $defaultVariationId = DB::table('snippet_variations')->insertGetId([
                    'snippet_id' => $snippet->id,
                    'created_by_id' => null,
                    'name' => 'Default',
                    'content' => $snippet->content,
                    'position' => 1,
                    'is_default' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            DB::table('snippet_variations')
                ->where('snippet_id', $snippet->id)
                ->update(['is_default' => false]);
            DB::table('snippet_variations')
                ->where('id', $defaultVariationId)
                ->update(['is_default' => true]);
        }

        Schema::table('snippet_variations', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->unique(['snippet_id', 'name']);
            $table->index(['snippet_id', 'is_default']);
        });

        Schema::table('snippets', function (Blueprint $table) {
            $table->dropColumn(['content', 'current_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('snippets', function (Blueprint $table) {
            $table->longText('content')->nullable();
            $table->unsignedInteger('current_version')->default(1);
        });

        $snippets = DB::table('snippets')->select('id')->orderBy('id')->get();

        foreach ($snippets as $snippet) {
            $defaultVariation = DB::table('snippet_variations')
                ->where('snippet_id', $snippet->id)
                ->orderByDesc('is_default')
                ->orderBy('position')
                ->first(['content', 'position']);

            DB::table('snippets')
                ->where('id', $snippet->id)
                ->update([
                    'content' => $defaultVariation->content ?? '',
                    'current_version' => $defaultVariation->position ?? 1,
                ]);
        }

        Schema::table('snippets', function (Blueprint $table) {
            $table->longText('content')->nullable(false)->change();
        });

        Schema::table('snippet_variations', function (Blueprint $table) {
            $table->dropUnique(['snippet_id', 'name']);
            $table->dropIndex(['snippet_id', 'is_default']);
            $table->dropColumn('is_default');
            $table->string('name')->nullable()->change();
            $table->renameColumn('position', 'number');
            $table->renameColumn('name', 'label');
        });

        Schema::rename('snippet_variations', 'snippet_versions');
    }
};
