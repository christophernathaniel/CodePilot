<?php

namespace App\Http\Controllers;

use App\Actions\Clipboards\CreateClipboardClip;
use App\Http\Requests\ClipboardClips\StoreClipboardClipRequest;
use App\Models\ClipboardClip;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ClipboardClipController extends Controller
{
    public function store(
        StoreClipboardClipRequest $request,
        CreateClipboardClip $createClipboardClip,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $createClipboardClip->handle($user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Content added to clipboard.')]);

        return back();
    }

    public function destroy(ClipboardClip $clipboardClip): RedirectResponse
    {
        Gate::authorize('delete', $clipboardClip);

        $clipboardClip->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Clip removed from clipboard.')]);

        return back();
    }
}
