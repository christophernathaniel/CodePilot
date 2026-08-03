<?php

namespace App\Http\Controllers;

use App\Actions\Library\MoveItemToTrash;
use App\Actions\Library\PermanentlyDeleteItem;
use App\Actions\Library\RestoreItemFromTrash;
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

    public function destroy(Project $project, Folder $folder, MoveItemToTrash $moveItemToTrash): RedirectResponse
    {
        Gate::authorize('delete', $folder);

        $moveItemToTrash->handle($folder);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder moved to Trash.')]);

        return back();
    }

    public function restore(Folder $folder, RestoreItemFromTrash $restoreItemFromTrash): RedirectResponse
    {
        abort_unless($folder->trashed(), 404);
        Gate::authorize('restore', $folder);

        $restoreItemFromTrash->handle($folder);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder restored.')]);

        return back();
    }

    public function forceDestroy(Folder $folder, PermanentlyDeleteItem $permanentlyDeleteItem): RedirectResponse
    {
        abort_unless($folder->trashed(), 404);
        Gate::authorize('forceDelete', $folder);

        $permanentlyDeleteItem->handle($folder);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder permanently deleted.')]);

        return back();
    }
}
