<?php

namespace Database\Factories;

use App\Models\Snippet;
use App\Models\SnippetVariation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SnippetVariation>
 */
class SnippetVariationFactory extends Factory
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
            'created_by_id' => User::factory(),
            'name' => 'Compact form',
            'content' => "const message = 'Hello, world!';",
            'position' => 1,
            'is_default' => false,
        ];
    }

    public function positioned(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
