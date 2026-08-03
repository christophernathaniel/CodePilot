<?php

namespace App\Actions\Library;

use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use Illuminate\Support\Facades\DB;

final class RestoreItemFromTrash
{
    public function handle(Project|Folder|Snippet $item): void
    {
        DB::transaction(function () use ($item): void {
            $deletionBatch = $item->getAttribute('deletion_batch');

            if (! is_string($deletionBatch) || $deletionBatch === '') {
                $item->restore();

                return;
            }

            if ($item instanceof Project) {
                $this->restoreProject($item, $deletionBatch);

                return;
            }

            if ($item instanceof Folder) {
                $this->restoreFolder($item, $deletionBatch);

                return;
            }

            $item->restore();
            $item->forceFill(['deletion_batch' => null])->save();
        }, attempts: 3);
    }

    private function restoreProject(Project $project, string $deletionBatch): void
    {
        $restoredAt = now();

        $project->restore();
        $project->forceFill(['deletion_batch' => null])->save();

        Folder::withTrashed()
            ->where('project_id', $project->id)
            ->where('deletion_batch', $deletionBatch)
            ->update([
                'deletion_batch' => null,
                'deleted_at' => null,
                'updated_at' => $restoredAt,
            ]);

        Snippet::withTrashed()
            ->where('project_id', $project->id)
            ->where('deletion_batch', $deletionBatch)
            ->update([
                'deletion_batch' => null,
                'deleted_at' => null,
                'updated_at' => $restoredAt,
            ]);
    }

    private function restoreFolder(Folder $folder, string $deletionBatch): void
    {
        $restoredAt = now();

        Folder::withTrashed()
            ->where('project_id', $folder->project_id)
            ->where('deletion_batch', $deletionBatch)
            ->update([
                'deletion_batch' => null,
                'deleted_at' => null,
                'updated_at' => $restoredAt,
            ]);

        Snippet::withTrashed()
            ->where('project_id', $folder->project_id)
            ->where('deletion_batch', $deletionBatch)
            ->update([
                'deletion_batch' => null,
                'deleted_at' => null,
                'updated_at' => $restoredAt,
            ]);
    }
}
