<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('projects')
            ->where(
                'description',
                'Copy-ready foreach patterns with version history and reusable variable presets.',
            )
            ->update([
                'description' => 'Copy-ready foreach patterns with named code variations and reusable variable presets.',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
