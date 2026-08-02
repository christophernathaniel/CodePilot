<?php

namespace App\Actions\Snippets;

use App\Models\Framework;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

class SyncProjectFrameworks
{
    /** @param list<string> $frameworkNames */
    public function handle(Project $project, User $user, array $frameworkNames): void
    {
        $frameworkIds = collect($frameworkNames)
            ->map(fn (string $frameworkName): string => Str::of($frameworkName)->trim()->squish()->toString())
            ->filter()
            ->map(fn (string $frameworkName): array => ['name' => $frameworkName, 'slug' => Str::slug($frameworkName)])
            ->filter(fn (array $framework): bool => $framework['slug'] !== '')
            ->mapWithKeys(fn (array $framework): array => [$framework['slug'] => $framework['name']])
            ->map(function (string $frameworkName, string $slug) use ($user): int {
                return Framework::query()->firstOrCreate(
                    ['user_id' => $user->id, 'slug' => $slug],
                    ['name' => $frameworkName, 'color' => '#64748b'],
                )->id;
            })
            ->values()
            ->all();

        $project->frameworks()->sync($frameworkIds);
    }
}
