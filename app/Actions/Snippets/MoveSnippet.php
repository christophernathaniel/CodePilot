<?php

namespace App\Actions\Snippets;

use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\User;
use App\Support\Snippets\SnippetLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveSnippet
{
    /** @param array{project_id: int|null, folder_id: int|null} $destination */
    public function handle(User $user, Snippet $snippet, array $destination): Snippet
    {
        return DB::transaction(function () use ($user, $snippet, $destination): Snippet {
            $lockedSnippet = Snippet::query()->lockForUpdate()->findOrFail($snippet->id);

            if ($lockedSnippet->user_id !== $user->id) {
                abort(403);
            }

            $projectId = $destination['project_id'];
            $folderId = $destination['folder_id'];

            if ($projectId === null && $folderId !== null) {
                throw ValidationException::withMessages([
                    'folder_id' => __('A standalone snippet cannot be placed in a folder.'),
                ]);
            }

            if ($projectId !== null) {
                Project::query()
                    ->whereKey($projectId)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ($folderId !== null) {
                Folder::query()
                    ->whereKey($folderId)
                    ->where('project_id', $projectId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $locationKey = SnippetLocation::key($projectId, $folderId);

            if (Snippet::query()
                ->where('user_id', $user->id)
                ->where('location_key', $locationKey)
                ->where('filename', $lockedSnippet->filename)
                ->whereKeyNot($lockedSnippet->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'filename' => __('A snippet with this filename already exists in that location.'),
                ]);
            }

            $position = ((int) Snippet::query()
                ->where('user_id', $user->id)
                ->where('location_key', $locationKey)
                ->max('position')) + 1;

            $lockedSnippet->update([
                'project_id' => $projectId,
                'folder_id' => $folderId,
                'location_key' => $locationKey,
                'position' => $position,
            ]);

            return $lockedSnippet;
        }, attempts: 3);
    }
}
