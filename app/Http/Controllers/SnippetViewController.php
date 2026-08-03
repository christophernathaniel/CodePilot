<?php

namespace App\Http\Controllers;

use App\Actions\Snippets\RecordSnippetView;
use App\Http\Requests\Snippets\RecordSnippetViewRequest;
use App\Models\Snippet;
use App\Models\User;
use Illuminate\Http\Response;

class SnippetViewController extends Controller
{
    public function __invoke(
        RecordSnippetViewRequest $request,
        Snippet $snippet,
        RecordSnippetView $recordSnippetView,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        $recordSnippetView->handle($user, $snippet, $request->validated());

        return response()->noContent();
    }
}
