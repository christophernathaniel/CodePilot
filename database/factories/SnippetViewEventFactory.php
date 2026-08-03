<?php

namespace Database\Factories;

use App\Models\Snippet;
use App\Models\SnippetViewEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SnippetViewEvent>
 */
class SnippetViewEventFactory extends Factory
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
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SnippetViewEvent $event): void {
            $snippet = Snippet::query()->find($event->snippet_id);

            if ($snippet !== null) {
                $event->user_id = $snippet->user_id;
            }
        });
    }
}
