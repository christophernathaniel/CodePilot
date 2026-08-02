<?php

namespace Database\Factories;

use App\Models\Framework;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Framework>
 */
class FrameworkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'slug' => fn (array $attributes): string => Str::slug($attributes['name']),
            'color' => fake()->hexColor(),
        ];
    }
}
