<?php

namespace App\Actions\Snippets;

use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\User;
use App\Support\Snippets\SnippetLocation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CreateSnippet
{
    public function __construct(
        private SyncSnippetTags $syncSnippetTags,
        private SyncSnippetFrameworks $syncSnippetFrameworks,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $user, array $attributes): Snippet
    {
        return DB::transaction(function () use ($user, $attributes): Snippet {
            $tagNames = Arr::pull($attributes, 'tags', []);
            $frameworkNames = Arr::pull($attributes, 'frameworks', []);
            $content = Arr::pull($attributes, 'content');
            $projectId = $attributes['project_id'] ?? null;
            $folderId = $attributes['folder_id'] ?? null;

            if ($projectId !== null) {
                Project::query()
                    ->whereKey($projectId)
                    ->where('user_id', $user->id)
                    ->firstOrFail();
            }

            if ($folderId !== null) {
                Folder::query()
                    ->whereKey($folderId)
                    ->where('project_id', $projectId)
                    ->firstOrFail();
            }

            $attributes['location_key'] = SnippetLocation::key($projectId, $folderId);
            $attributes['position'] = ((int) $user->snippets()
                ->where('location_key', $attributes['location_key'])
                ->max('position')) + 1;

            $snippet = $user->snippets()->create($attributes);

            $snippet->variations()->create([
                'created_by_id' => $user->id,
                'name' => 'Default',
                'content' => $content,
                'position' => 1,
                'is_default' => true,
            ]);

            $this->syncSnippetTags->handle($snippet, $user, $tagNames);
            $this->syncSnippetFrameworks->handle($snippet, $user, $frameworkNames);

            return $snippet;
        }, attempts: 3);
    }
}
