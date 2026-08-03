<?php

namespace App\Actions\Snippets;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderProjects
{
    /** @param list<int> $projectIds */
    public function handle(User $user, array $projectIds): void
    {
        DB::transaction(function () use ($user, $projectIds): void {
            $lockedProjects = Project::query()
                ->whereBelongsTo($user)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $ownedProjectIds = $lockedProjects->pluck('id')->all();
            $submittedProjectIds = array_values($projectIds);
            $sortedSubmittedProjectIds = $submittedProjectIds;

            sort($sortedSubmittedProjectIds);

            if ($sortedSubmittedProjectIds !== $ownedProjectIds) {
                throw ValidationException::withMessages([
                    'project_ids' => __('The project order is out of date. Refresh the workspace and try again.'),
                ]);
            }

            $projectsById = $lockedProjects->keyBy('id');

            foreach ($submittedProjectIds as $position => $projectId) {
                $projectsById->get($projectId)?->update(['position' => $position]);
            }
        }, attempts: 3);
    }
}
