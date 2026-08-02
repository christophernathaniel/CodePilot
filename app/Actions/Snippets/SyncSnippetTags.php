<?php

namespace App\Actions\Snippets;

use App\Models\Snippet;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Str;

final class SyncSnippetTags
{
    /** @param list<string> $tagNames */
    public function handle(Snippet $snippet, User $user, array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->map(fn (string $tagName): string => Str::of($tagName)->trim()->squish()->toString())
            ->filter()
            ->map(fn (string $tagName): array => ['name' => $tagName, 'slug' => Str::slug($tagName)])
            ->filter(fn (array $tag): bool => $tag['slug'] !== '')
            ->mapWithKeys(fn (array $tag): array => [$tag['slug'] => $tag['name']])
            ->map(function (string $tagName, string $slug) use ($user): int {
                return Tag::query()->firstOrCreate(
                    ['user_id' => $user->id, 'slug' => $slug],
                    ['name' => $tagName, 'color' => '#6b7280'],
                )->id;
            })
            ->values()
            ->all();

        $snippet->tags()->sync($tagIds);
    }
}
