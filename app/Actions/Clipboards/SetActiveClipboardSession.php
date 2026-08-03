<?php

namespace App\Actions\Clipboards;

use App\Models\ClipboardSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetActiveClipboardSession
{
    public function handle(User $user, ClipboardSession $clipboardSession): void
    {
        DB::transaction(function () use ($user, $clipboardSession): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $ownedClipboardSession = $lockedUser->clipboardSessions()->findOrFail($clipboardSession->id);

            $lockedUser->clipboardSessions()->update(['is_active' => false]);
            $ownedClipboardSession->update(['is_active' => true]);
        }, attempts: 3);
    }
}
