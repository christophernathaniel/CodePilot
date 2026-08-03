<?php

namespace App\Actions\Clipboards;

use App\Models\ClipboardSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateClipboardSession
{
    public function handle(User $user, ?string $name): ClipboardSession
    {
        return DB::transaction(function () use ($user, $name): ClipboardSession {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $resolvedName = $name ?? $this->nextAvailableName($lockedUser);

            $lockedUser->clipboardSessions()->update(['is_active' => false]);

            return $lockedUser->clipboardSessions()->create([
                'name' => $resolvedName,
                'is_active' => true,
            ]);
        }, attempts: 3);
    }

    private function nextAvailableName(User $user): string
    {
        $names = $user->clipboardSessions()->pluck('name')->flip();
        $number = 1;

        while ($names->has('Clipboard '.$number)) {
            $number++;
        }

        return 'Clipboard '.$number;
    }
}
