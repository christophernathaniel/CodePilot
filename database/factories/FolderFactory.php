<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'parent_id' => null,
            'name' => Str::of(fake()->unique()->sentence(2))->rtrim('.')->title()->toString(),
            'position' => 0,
        ];
    }

    public function nestedUnder(Folder $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $parent->project_id,
            'parent_id' => $parent->id,
        ]);
    }
}
