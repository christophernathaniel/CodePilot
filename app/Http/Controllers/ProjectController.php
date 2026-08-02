<?php

namespace App\Http\Controllers;

use App\Actions\Snippets\SyncProjectFrameworks;
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

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('dashboard');
    }
}
