<?php

namespace App\Http\Controllers;

use App\Actions\Snippets\MoveSnippet;
use App\Http\Requests\Snippets\MoveSnippetRequest;
use App\Models\Snippet;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MoveSnippetController extends Controller
{
    public function __invoke(
        MoveSnippetRequest $request,
        Snippet $snippet,
        MoveSnippet $moveSnippet,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->safe();

        $moveSnippet->handle($user, $snippet, [
            'project_id' => $validated->input('project_id') === null
                ? null
                : $validated->integer('project_id'),
            'folder_id' => $validated->input('folder_id') === null
                ? null
                : $validated->integer('folder_id'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Snippet moved.')]);

        return back();
    }
}
