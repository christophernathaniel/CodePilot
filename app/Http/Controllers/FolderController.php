<?php

namespace App\Http\Controllers;

use App\Http\Requests\Folders\StoreFolderRequest;
use App\Http\Requests\Folders\UpdateFolderRequest;
use App\Models\Folder;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class FolderController extends Controller
{
    public function store(StoreFolderRequest $request, Project $project): RedirectResponse
    {
        $project->folders()->create([
            ...$request->validated(),
            'position' => ((int) $project->folders()->max('position')) + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder created.')]);

        return back();
    }

    public function update(UpdateFolderRequest $request, Project $project, Folder $folder): RedirectResponse
    {
        $folder->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder updated.')]);

        return back();
    }

    public function destroy(Project $project, Folder $folder): RedirectResponse
    {
        Gate::authorize('delete', $folder);

        $folder->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder deleted.')]);

        return back();
    }
}
