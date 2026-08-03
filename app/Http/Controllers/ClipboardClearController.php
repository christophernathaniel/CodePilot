<?php

namespace App\Http\Controllers;

use App\Models\ClipboardSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ClipboardClearController extends Controller
{
    public function __invoke(ClipboardSession $clipboardSession): RedirectResponse
    {
        Gate::authorize('update', $clipboardSession);

        $clipboardSession->clips()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Clipboard cleared.')]);

        return back();
    }
}
