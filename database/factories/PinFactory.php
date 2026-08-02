<?php

namespace Database\Factories;

use App\Models\Pin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pin>
 */
class PinFactory extends Factory
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
            'pinnable_type' => 'language',
            'pinnable_key' => fake()->unique()->randomElement(['php', 'twig', 'javascript', 'typescript', 'scss']),
        ];
    }
}
