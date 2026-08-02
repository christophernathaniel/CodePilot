<?php

namespace Database\Factories;

use App\Models\Snippet;
use App\Models\VariablePreset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VariablePreset>
 */
class VariablePresetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'snippet_id' => Snippet::factory(),
            'name' => Str::of(fake()->unique()->sentence(2))->rtrim('.')->toString(),
            'values' => [
                'base_url' => fake()->url(),
                'api_token' => fake()->uuid(),
            ],
        ];
    }
}
