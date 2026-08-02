<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Snippet>
 */
class SnippetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $samples = [
            [
                'language' => 'javascript',
                'extension' => 'js',
            ],
            [
                'language' => 'php',
                'extension' => 'php',
            ],
            [
                'language' => 'css',
                'extension' => 'css',
            ],
            [
                'language' => 'sql',
                'extension' => 'sql',
            ],
        ];
        $sample = $samples[fake()->numberBetween(0, count($samples) - 1)];
        $name = Str::of(fake()->unique()->sentence(2))->rtrim('.')->toString();

        return [
            'user_id' => User::factory(),
            'project_id' => null,
            'folder_id' => null,
            'location_key' => 'standalone',
            'title' => Str::title($name),
            'filename' => Str::slug($name).'.'.$sample['extension'],
            'language' => $sample['language'],
            'content_type' => Snippet::CONTENT_TYPE_SNIPPET,
            'description' => fake()->optional()->sentence(),
            'position' => 0,
            'last_opened_at' => null,
        ];
    }

    public function inFolder(Folder $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $folder->project()->value('user_id'),
            'project_id' => $folder->project_id,
            'folder_id' => $folder->id,
            'location_key' => 'folder:'.$folder->id,
        ]);
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $project->user_id,
            'project_id' => $project->id,
            'folder_id' => null,
            'location_key' => 'project:'.$project->id,
        ]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Snippet $snippet): void {
            if ($snippet->project_id === null) {
                $snippet->folder_id = null;
                $snippet->location_key = 'standalone';

                return;
            }

            $project = Project::query()->find($snippet->project_id);

            if ($project !== null) {
                $snippet->user_id = $project->user_id;
                $snippet->location_key = $snippet->folder_id !== null
                    ? 'folder:'.$snippet->folder_id
                    : 'project:'.$project->id;
            }
        });
    }

    public function withTemplateVariables(): static
    {
        return $this
            ->state(fn (array $attributes) => [
                'title' => 'Fetch API Client',
                'filename' => 'fetch-client.js',
                'language' => 'javascript',
            ])
            ->withVariation(
                "const response = await fetch('{{{base_url:https://api.example.com}}}/users', {\n    headers: { Authorization: 'Bearer {{{api_token:demo-token}}}' },\n});",
            );
    }

    public function guide(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => Snippet::CONTENT_TYPE_GUIDE,
            'language' => 'markdown',
        ]);
    }

    public function withVariation(
        string $content = "const message = 'Hello, world!';",
        string $name = 'Default',
        bool $isDefault = true,
    ): static {
        return $this->afterCreating(function (Snippet $snippet) use ($content, $name, $isDefault): void {
            $snippet->variations()->create([
                'created_by_id' => $snippet->user_id,
                'name' => $name,
                'content' => $content,
                'position' => ((int) $snippet->variations()->max('position')) + 1,
                'is_default' => $isDefault,
            ]);
        });
    }
}
