<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage staff');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo('manage staff') || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage staff');
    }

    public function update(User $user, User $model): bool
    {
        // Prevent updating the Super Admin unless it's the Super Admin themselves updating their own basic info (not roles)
        if ($model->hasRole('Super Admin') && $user->id !== $model->id) {
            return false;
        }
        return $user->hasPermissionTo('manage staff') || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        // NEVER allow deleting a Super Admin
        if ($model->hasRole('Super Admin')) {
            return false;
        }
        // Prevent deleting oneself
        if ($user->id === $model->id) {
            return false;
        }
        return $user->hasPermissionTo('manage staff');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
