<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        // Only admins can view user lists
        return $user->hasPermissionTo('users.view') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile, admins can view any
        return $user->hasPermissionTo('users.view')
               && ($user->hasRole('admin') || $user->id === $model->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only admins can create users (registration is handled separately)
        return $user->hasPermissionTo('users.create') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile, admins can update any
        return $user->hasPermissionTo('users.update')
               && ($user->hasRole('admin') || $user->id === $model->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Only admins can delete users, but not themselves
        return $user->hasPermissionTo('users.delete')
               && $user->hasRole('admin')
               && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        // Only admins can restore users
        return $user->hasPermissionTo('users.update') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Only admins can permanently delete users, but not themselves
        return $user->hasPermissionTo('users.delete')
               && $user->hasRole('admin')
               && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can assign roles.
     */
    public function assignRole(User $user, User $model): bool
    {
        // Only admins can assign roles, but not to themselves
        return $user->hasPermissionTo('system.admin')
               && $user->hasRole('admin')
               && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can remove roles.
     */
    public function removeRole(User $user, User $model): bool
    {
        // Only admins can remove roles, but not from themselves
        return $user->hasPermissionTo('system.admin')
               && $user->hasRole('admin')
               && $user->id !== $model->id;
    }
}
