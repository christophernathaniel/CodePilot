<?php

namespace App\Actions\Library;

use App\Models\Folder;
use App\Models\Pin;
use App\Models\Project;
use App\Models\Snippet;
use Illuminate\Support\Facades\DB;

final class PermanentlyDeleteItem
{
    public function handle(Project|Folder|Snippet $item): void
    {
        DB::transaction(function () use ($item): void {
            if ($item instanceof Project) {
                $this->deletePins($item->user_id, 'project', [$item->id]);
                $this->deletePins(
                    $item->user_id,
                    'snippet',
                    Snippet::withTrashed()
                        ->where('project_id', $item->id)
                        ->pluck('id')
                        ->all(),
                );
            } elseif ($item instanceof Folder) {
                $project = $item->project()->withTrashed()->firstOrFail();
                $folderIds = $this->descendantFolderIds($item);

                $this->deletePins(
                    $project->user_id,
                    'snippet',
                    Snippet::withTrashed()
                        ->whereIn('folder_id', $folderIds)
                        ->pluck('id')
                        ->all(),
                );
            } else {
                $this->deletePins($item->user_id, 'snippet', [$item->id]);
            }

            $item->forceDelete();
        }, attempts: 3);
    }

    /** @param list<int> $keys */
    private function deletePins(int $userId, string $type, array $keys): void
    {
        if ($keys === []) {
            return;
        }

        Pin::query()
            ->where('user_id', $userId)
            ->where('pinnable_type', $type)
            ->whereIn('pinnable_key', array_map('strval', $keys))
            ->delete();
    }

    /** @return list<int> */
    private function descendantFolderIds(Folder $folder): array
    {
        $childrenByParent = Folder::withTrashed()
            ->where('project_id', $folder->project_id)
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');
        $folderIds = [];
        $pendingFolderIds = [$folder->id];

        while ($pendingFolderIds !== []) {
            $folderId = array_pop($pendingFolderIds);
            $folderIds[] = $folderId;

            foreach ($childrenByParent->get($folderId, collect()) as $child) {
                $pendingFolderIds[] = $child->id;
            }
        }

        return $folderIds;
    }
}
