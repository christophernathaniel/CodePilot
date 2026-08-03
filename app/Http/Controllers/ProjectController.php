<?php

namespace App\Http\Controllers;

use App\Actions\Library\MoveItemToTrash;
use App\Actions\Library\PermanentlyDeleteItem;
use App\Actions\Library\RestoreItemFromTrash;
use App\Actions\Snippets\ReorderProjects;
use App\Actions\Snippets\SyncProjectFrameworks;
use App\Http\Requests\Projects\ReorderProjectsRequest;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function reorder(
        ReorderProjectsRequest $request,
        ReorderProjects $reorderProjects,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        /** @var list<int|string> $validatedProjectIds */
        $validatedProjectIds = $validated['project_ids'];
        /** @var list<int> $projectIds */
        $projectIds = array_map(
            static fn (int|string $projectId): int => (int) $projectId,
            $validatedProjectIds,
        );

        $reorderProjects->handle($user, $projectIds);

        return back();
    }

    public function store(
        StoreProjectRequest $request,
        SyncProjectFrameworks $syncProjectFrameworks,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $attributes = $request->validated();
        /** @var list<string> $frameworkNames */
        $frameworkNames = Arr::pull($attributes, 'frameworks', []);

        DB::transaction(function () use ($user, $attributes, $frameworkNames, $syncProjectFrameworks): void {
            $project = $user->projects()->create([
                ...$attributes,
                'position' => ((int) $user->projects()->max('position')) + 1,
            ]);

            $syncProjectFrameworks->handle($project, $user, $frameworkNames);
        }, attempts: 3);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return back();
    }

    public function update(
        UpdateProjectRequest $request,
        Project $project,
        SyncProjectFrameworks $syncProjectFrameworks,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $attributes = $request->validated();
        /** @var list<string>|null $frameworkNames */
        $frameworkNames = Arr::pull($attributes, 'frameworks');

        DB::transaction(function () use ($project, $user, $attributes, $frameworkNames, $syncProjectFrameworks): void {
            $project->update($attributes);

            if ($frameworkNames !== null) {
                $syncProjectFrameworks->handle($project, $user, $frameworkNames);
            }
        }, attempts: 3);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return back();
    }

    public function destroy(Project $project, MoveItemToTrash $moveItemToTrash): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $moveItemToTrash->handle($project);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project moved to Trash.')]);

        return to_route('dashboard');
    }

    public function restore(Project $project, RestoreItemFromTrash $restoreItemFromTrash): RedirectResponse
    {
        abort_unless($project->trashed(), 404);
        Gate::authorize('restore', $project);

        $restoreItemFromTrash->handle($project);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project restored.')]);

        return back();
    }

    public function forceDestroy(Project $project, PermanentlyDeleteItem $permanentlyDeleteItem): RedirectResponse
    {
        abort_unless($project->trashed(), 404);
        Gate::authorize('forceDelete', $project);

        $permanentlyDeleteItem->handle($project);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project permanently deleted.')]);

        return back();
    }
}
