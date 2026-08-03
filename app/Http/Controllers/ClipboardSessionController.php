<?php

namespace App\Http\Controllers;

use App\Actions\Clipboards\CreateClipboardSession;
use App\Actions\Clipboards\DeleteClipboardSession;
use App\Http\Requests\ClipboardSessions\StoreClipboardSessionRequest;
use App\Http\Requests\ClipboardSessions\UpdateClipboardSessionRequest;
use App\Models\ClipboardSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ClipboardSessionController extends Controller
{
    public function store(
        StoreClipboardSessionRequest $request,
        CreateClipboardSession $createClipboardSession,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $name = $request->safe()->only(['name'])['name'] ?? null;

        $createClipboardSession->handle($user, is_string($name) ? $name : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Clipboard created.')]);

        return back();
    }

    public function update(
        UpdateClipboardSessionRequest $request,
        ClipboardSession $clipboardSession,
    ): RedirectResponse {
        $clipboardSession->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Clipboard renamed.')]);

        return back();
    }

    public function destroy(
        Request $request,
        ClipboardSession $clipboardSession,
        DeleteClipboardSession $deleteClipboardSession,
    ): RedirectResponse {
        Gate::authorize('delete', $clipboardSession);
        /** @var User $user */
        $user = $request->user();

        $deleteClipboardSession->handle($user, $clipboardSession);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Clipboard deleted.')]);

        return back();
    }
}
