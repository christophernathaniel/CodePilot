<?php

namespace App\Http\Controllers;

use App\Actions\Snippets\CreateSnippet;
use App\Actions\Snippets\SyncSnippetFrameworks;
use App\Actions\Snippets\SyncSnippetTags;
use App\Http\Requests\Snippets\StoreSnippetRequest;
use App\Http\Requests\Snippets\UpdateSnippetRequest;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SnippetController extends Controller
{
    public function store(
        StoreSnippetRequest $request,
        CreateSnippet $createSnippet,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $createSnippet->handle($user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Snippet created.')]);

        return back();
    }

    public function storeInProject(
        StoreSnippetRequest $request,
        Project $project,
        CreateSnippet $createSnippet,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $attributes = $request->validated();
        $attributes['project_id'] = $project->id;

        $createSnippet->handle($user, $attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Snippet created.')]);

        return back();
    }

    public function update(
        UpdateSnippetRequest $request,
        Snippet $snippet,
        SyncSnippetTags $syncSnippetTags,
        SyncSnippetFrameworks $syncSnippetFrameworks,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $attributes = $request->validated();
        $tagNames = Arr::pull($attributes, 'tags', []);
        $frameworkNames = Arr::pull($attributes, 'frameworks', []);

        DB::transaction(function () use (
            $snippet,
            $user,
            $attributes,
            $tagNames,
            $syncSnippetTags,
            $frameworkNames,
            $syncSnippetFrameworks,
        ): void {
            $snippet->update($attributes);
            $syncSnippetTags->handle($snippet, $user, $tagNames);
            $syncSnippetFrameworks->handle($snippet, $user, $frameworkNames);
        }, attempts: 3);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Snippet saved.')]);

        return back();
    }

    public function destroy(Snippet $snippet): RedirectResponse
    {
        Gate::authorize('delete', $snippet);

        $snippet->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Snippet deleted.')]);

        return back();
    }
}
