<?php

namespace App\Http\Controllers;

use App\Actions\Snippets\RecordSnippetCopy;
use App\Http\Requests\Snippets\RecordSnippetCopyRequest;
use App\Models\Snippet;
use App\Models\User;
use Illuminate\Http\Response;

class SnippetUsageController extends Controller
{
    public function __invoke(
        RecordSnippetCopyRequest $request,
        Snippet $snippet,
        RecordSnippetCopy $recordSnippetCopy,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        $recordSnippetCopy->handle($user, $snippet, $request->validated());

        return response()->noContent();
    }
}
