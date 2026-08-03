<?php

namespace App\Http\Controllers;

use App\Actions\Clipboards\SetActiveClipboardSession;
use App\Models\ClipboardSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ClipboardActivationController extends Controller
{
    public function __invoke(
        Request $request,
        ClipboardSession $clipboardSession,
        SetActiveClipboardSession $setActiveClipboardSession,
    ): RedirectResponse {
        Gate::authorize('update', $clipboardSession);
        /** @var User $user */
        $user = $request->user();

        $setActiveClipboardSession->handle($user, $clipboardSession);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Clipboard activated.')]);

        return back();
    }
}
