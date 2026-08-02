<?php

namespace App\Http\Controllers;

use App\Http\Requests\Snippets\UpdateSnippetFavouriteRequest;
use App\Models\Snippet;
use Illuminate\Http\RedirectResponse;

class SnippetFavouriteController extends Controller
{
    public function __invoke(UpdateSnippetFavouriteRequest $request, Snippet $snippet): RedirectResponse
    {
        $snippet->update($request->safe()->only(['is_favourite']));

        return back();
    }
}
