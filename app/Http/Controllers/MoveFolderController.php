<?php

namespace App\Http\Controllers;

use App\Actions\Snippets\MoveFolder;
use App\Http\Requests\Folders\MoveFolderRequest;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MoveFolderController extends Controller
{
    public function __invoke(
        MoveFolderRequest $request,
        Folder $folder,
        MoveFolder $moveFolder,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->safe();

        $moveFolder->handle($user, $folder, [
            'project_id' => $validated->integer('project_id'),
            'parent_id' => $validated->input('parent_id') === null
                ? null
                : $validated->integer('parent_id'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder moved.')]);

        return back();
    }
}
