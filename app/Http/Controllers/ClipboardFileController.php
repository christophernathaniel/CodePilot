<?php

namespace App\Http\Controllers;

use App\Actions\Clipboards\CreateSnippetFromClipboard;
use App\Http\Requests\ClipboardFiles\StoreClipboardFileRequest;
use App\Models\ClipboardSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ClipboardFileController extends Controller
{
    public function __invoke(
        StoreClipboardFileRequest $request,
        ClipboardSession $clipboardSession,
        CreateSnippetFromClipboard $createSnippetFromClipboard,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $createSnippetFromClipboard->handle($user, $clipboardSession, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Clipboard saved as a file.')]);

        return back();
    }
}
