<?php

namespace App\Http\Controllers;

use App\Actions\Snippets\CreateSnippetVariation;
use App\Actions\Snippets\SetDefaultSnippetVariation;
use App\Http\Requests\SnippetVariations\StoreSnippetVariationRequest;
use App\Http\Requests\SnippetVariations\UpdateSnippetVariationRequest;
use App\Models\Snippet;
use App\Models\SnippetVariation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SnippetVariationController extends Controller
{
    public function store(
        StoreSnippetVariationRequest $request,
        Snippet $snippet,
        CreateSnippetVariation $createSnippetVariation,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->safe();

        $createSnippetVariation->handle($snippet, $user, [
            'name' => $validated->string('name')->toString(),
            'content' => $validated->string('content')->toString(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variation created.')]);

        return back();
    }

    public function update(
        UpdateSnippetVariationRequest $request,
        Snippet $snippet,
        SnippetVariation $snippetVariation,
    ): RedirectResponse {
        $snippetVariation->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variation saved.')]);

        return back();
    }

    public function destroy(Snippet $snippet, SnippetVariation $snippetVariation): RedirectResponse
    {
        Gate::authorize('delete', $snippetVariation);
        abort_if(
            $snippetVariation->is_default || $snippet->variations()->count() <= 1,
            422,
            __('Choose another default variation before deleting this one.'),
        );

        $snippetVariation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variation deleted.')]);

        return back();
    }

    public function makeDefault(
        Snippet $snippet,
        SnippetVariation $snippetVariation,
        SetDefaultSnippetVariation $setDefaultSnippetVariation,
    ): RedirectResponse {
        Gate::authorize('update', $snippetVariation);

        $setDefaultSnippetVariation->handle($snippetVariation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default variation updated.')]);

        return back();
    }
}
