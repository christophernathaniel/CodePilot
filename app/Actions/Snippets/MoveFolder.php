<?php

namespace App\Actions\Snippets;

use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveFolder
{
    /** @param array{project_id: int, parent_id: int|null} $destination */
    public function handle(User $user, Folder $folder, array $destination): Folder
    {
        return DB::transaction(function () use ($user, $folder, $destination): Folder {
            $lockedFolder = Folder::query()->lockForUpdate()->findOrFail($folder->id);
            $sourceProject = Project::query()->lockForUpdate()->findOrFail($lockedFolder->project_id);

            if ($sourceProject->user_id !== $user->id) {
                abort(403);
            }

            $targetProject = Project::query()
                ->whereKey($destination['project_id'])
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $subtreeIds = $this->subtreeIds($lockedFolder);
            $parentId = $destination['parent_id'];

            if ($parentId !== null) {
                if ($subtreeIds->contains($parentId)) {
                    throw ValidationException::withMessages([
                        'parent_id' => __('A folder cannot be moved inside itself or one of its descendants.'),
                    ]);
                }

                Folder::query()
                    ->whereKey($parentId)
                    ->where('project_id', $targetProject->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if (Folder::query()
                ->where('project_id', $targetProject->id)
                ->where('parent_id', $parentId)
                ->where('name', $lockedFolder->name)
                ->whereKeyNot($lockedFolder->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'name' => __('A folder with this name already exists in that location.'),
                ]);
            }

            $position = ((int) Folder::query()
                ->where('project_id', $targetProject->id)
                ->where('parent_id', $parentId)
                ->max('position')) + 1;

            Folder::query()
                ->whereIn('id', $subtreeIds)
                ->update(['project_id' => $targetProject->id]);

            Snippet::query()
                ->whereIn('folder_id', $subtreeIds)
                ->update(['project_id' => $targetProject->id]);

            $lockedFolder->update([
                'project_id' => $targetProject->id,
                'parent_id' => $parentId,
                'position' => $position,
            ]);

            return $lockedFolder;
        }, attempts: 3);
    }

    /** @return Collection<int, int> */
    private function subtreeIds(Folder $root): Collection
    {
        $folders = Folder::query()
            ->where('project_id', $root->project_id)
            ->get(['id', 'parent_id']);
        $childrenByParent = $folders->groupBy('parent_id');
        $ids = collect([$root->id]);

        for ($index = 0; $index < $ids->count(); $index++) {
            $folderId = $ids->get($index);

            foreach ($childrenByParent->get($folderId, collect()) as $child) {
                if (! $ids->contains($child->id)) {
                    $ids->push($child->id);
                }
            }
        }

        return $ids;
    }
}
