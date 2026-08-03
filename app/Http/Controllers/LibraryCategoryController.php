<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryCategories\StoreLibraryCategoryRequest;
use App\Http\Requests\LibraryCategories\UpdateLibraryCategoryRequest;
use App\Models\LibraryCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LibraryCategoryController extends Controller
{
    public function store(StoreLibraryCategoryRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->libraryCategories()->create([
            ...$request->validated(),
            'position' => ((int) $user->libraryCategories()->max('position')) + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return back();
    }

    public function update(
        UpdateLibraryCategoryRequest $request,
        LibraryCategory $libraryCategory,
    ): RedirectResponse {
        $libraryCategory->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return back();
    }

    public function destroy(LibraryCategory $libraryCategory): RedirectResponse
    {
        Gate::authorize('delete', $libraryCategory);

        $libraryCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted. Its workspaces are now uncategorised.')]);

        return back();
    }
}
