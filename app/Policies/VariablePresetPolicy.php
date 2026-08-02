<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VariablePreset;

class VariablePresetPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VariablePreset $variablePreset): bool
    {
        return $this->belongsToUser($variablePreset, $user);
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
    public function update(User $user, VariablePreset $variablePreset): bool
    {
        return $this->belongsToUser($variablePreset, $user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VariablePreset $variablePreset): bool
    {
        return $this->belongsToUser($variablePreset, $user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VariablePreset $variablePreset): bool
    {
        return $this->belongsToUser($variablePreset, $user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VariablePreset $variablePreset): bool
    {
        return false;
    }

    private function belongsToUser(VariablePreset $variablePreset, User $user): bool
    {
        return $variablePreset->snippet()
            ->where('user_id', $user->id)
            ->exists();
    }
}
