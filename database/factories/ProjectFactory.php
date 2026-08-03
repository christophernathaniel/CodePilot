<?php

namespace Database\Factories;

use App\Models\LibraryCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::of(fake()->unique()->sentence(3))->rtrim('.')->title()->toString();

        return [
            'user_id' => User::factory(),
            'kind' => 'project',
            'name' => $name,
            'description' => fake()->optional()->sentence(),
            'position' => 0,
        ];
    }

    public function bundle(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => Project::KIND_BUNDLE,
        ]);
    }

    public function guide(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => Project::KIND_GUIDE,
        ]);
    }

    public function inLibraryCategory(LibraryCategory $libraryCategory): static
    {
        return $this->for($libraryCategory, 'libraryCategory');
    }
}
