<?php

namespace App\Actions\Library;

use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MoveItemToTrash
{
    public function handle(Project|Folder|Snippet $item): void
    {
        DB::transaction(function () use ($item): void {
            $deletionBatch = (string) Str::uuid();

            if ($item instanceof Project) {
                $this->moveProject($item, $deletionBatch);

                return;
            }

            if ($item instanceof Folder) {
                $this->moveFolder($item, $deletionBatch);

                return;
            }

            $item->forceFill(['deletion_batch' => $deletionBatch])->save();
            $item->delete();
        }, attempts: 3);
    }

    private function moveProject(Project $project, string $deletionBatch): void
    {
        $deletedAt = now();

        $project->forceFill(['deletion_batch' => $deletionBatch])->save();
        $project->delete();

        Folder::query()
            ->where('project_id', $project->id)
            ->update([
                'deletion_batch' => $deletionBatch,
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]);

        Snippet::query()
            ->where('project_id', $project->id)
            ->update([
                'deletion_batch' => $deletionBatch,
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]);
    }

    private function moveFolder(Folder $folder, string $deletionBatch): void
    {
        $deletedAt = now();
        $folderIds = $this->descendantFolderIds($folder);

        $folder->forceFill(['deletion_batch' => $deletionBatch])->save();
        $folder->delete();

        Folder::query()
            ->whereIn('id', $folderIds)
            ->update([
                'deletion_batch' => $deletionBatch,
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]);

        Snippet::query()
            ->whereIn('folder_id', $folderIds)
            ->update([
                'deletion_batch' => $deletionBatch,
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]);
    }

    /** @return list<int> */
    private function descendantFolderIds(Folder $folder): array
    {
        $childrenByParent = Folder::query()
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
