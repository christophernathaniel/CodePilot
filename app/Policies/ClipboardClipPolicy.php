<?php

namespace App\Policies;

use App\Models\ClipboardClip;
use App\Models\User;

class ClipboardClipPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ClipboardClip $clipboardClip): bool
    {
        return $this->belongsToUser($clipboardClip, $user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ClipboardClip $clipboardClip): bool
    {
        return $this->belongsToUser($clipboardClip, $user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ClipboardClip $clipboardClip): bool
    {
        return $this->belongsToUser($clipboardClip, $user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ClipboardClip $clipboardClip): bool
    {
        return $this->belongsToUser($clipboardClip, $user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ClipboardClip $clipboardClip): bool
    {
        return false;
    }

    private function belongsToUser(ClipboardClip $clipboardClip, User $user): bool
    {
        return $clipboardClip->clipboardSession()
            ->where('user_id', $user->id)
            ->exists();
    }
}
