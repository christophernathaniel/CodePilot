<?php

namespace App\Policies;

use App\Models\SnippetVariation;
use App\Models\User;

class SnippetVariationPolicy
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
    public function view(User $user, SnippetVariation $snippetVariation): bool
    {
        return $this->belongsToUser($snippetVariation, $user);
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
    public function update(User $user, SnippetVariation $snippetVariation): bool
    {
        return $this->belongsToUser($snippetVariation, $user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SnippetVariation $snippetVariation): bool
    {
        return $this->belongsToUser($snippetVariation, $user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SnippetVariation $snippetVariation): bool
    {
        return false;
    }

    private function belongsToUser(SnippetVariation $snippetVariation, User $user): bool
    {
        return $snippetVariation->snippet()
            ->where('user_id', $user->id)
            ->exists();
    }
}
