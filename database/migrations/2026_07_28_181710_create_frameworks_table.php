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
        Schema::create('frameworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 7)->default('#64748b');
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'name']);
        });

        $createdAt = now();

        DB::table('users')->pluck('id')->each(function (int $userId) use ($createdAt): void {
            DB::table('frameworks')->insert([
                ['user_id' => $userId, 'name' => 'WordPress', 'slug' => 'wordpress', 'color' => '#60a5fa', 'created_at' => $createdAt, 'updated_at' => $createdAt],
                ['user_id' => $userId, 'name' => 'Laravel', 'slug' => 'laravel', 'color' => '#94a3b8', 'created_at' => $createdAt, 'updated_at' => $createdAt],
                ['user_id' => $userId, 'name' => 'React', 'slug' => 'react', 'color' => '#38bdf8', 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frameworks');
    }
};
