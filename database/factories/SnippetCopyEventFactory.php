<?php

namespace Database\Factories;

use App\Models\Snippet;
use App\Models\SnippetCopyEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SnippetCopyEvent>
 */
class SnippetCopyEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'snippet_id' => Snippet::factory(),
            'snippet_variation_id' => null,
            'variable_preset_id' => null,
            'method' => 'button',
            'representation' => 'source',
            'scope' => 'full',
            'selection_length' => fake()->numberBetween(1, 1000),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SnippetCopyEvent $event): void {
            $snippet = Snippet::query()->find($event->snippet_id);

            if ($snippet !== null) {
                $event->user_id = $snippet->user_id;
            }
        });
    }
}
