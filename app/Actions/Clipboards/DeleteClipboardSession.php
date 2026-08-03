<?php

namespace App\Actions\Clipboards;

use App\Models\ClipboardSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteClipboardSession
{
    public function handle(User $user, ClipboardSession $clipboardSession): void
    {
        DB::transaction(function () use ($user, $clipboardSession): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $ownedClipboardSession = $lockedUser->clipboardSessions()->findOrFail($clipboardSession->id);
            $wasActive = $ownedClipboardSession->is_active;

            $ownedClipboardSession->delete();

            if (! $wasActive) {
                return;
            }

            $lockedUser->clipboardSessions()->update(['is_active' => false]);
            $fallback = $lockedUser->clipboardSessions()->orderBy('id')->first();
            $fallback?->update(['is_active' => true]);
        }, attempts: 3);
    }
}
