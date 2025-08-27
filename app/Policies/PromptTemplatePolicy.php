<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PromptTemplate;
use App\Models\User;

class PromptTemplatePolicy
{
    /**
     * Determine whether the user can view any prompt templates.
     */
    public function viewAny(User $user): bool
    {
        // Only admins can view prompt template lists
        return $user->hasPermissionTo('system.admin') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the prompt template.
     */
    public function view(User $user, PromptTemplate $promptTemplate): bool
    {
        // Only admins can view prompt templates
        return $user->hasPermissionTo('system.admin') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create prompt templates.
     */
    public function create(User $user): bool
    {
        // Only admins can create prompt templates
        return $user->hasPermissionTo('system.admin') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the prompt template.
     */
    public function update(User $user, PromptTemplate $promptTemplate): bool
    {
        // Only admins can update prompt templates
        return $user->hasPermissionTo('system.admin') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the prompt template.
     */
    public function delete(User $user, PromptTemplate $promptTemplate): bool
    {
        // Only admins can delete prompt templates
        return $user->hasPermissionTo('system.admin') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the prompt template.
     */
    public function restore(User $user, PromptTemplate $promptTemplate): bool
    {
        // Only admins can restore prompt templates
        return $user->hasPermissionTo('system.admin') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the prompt template.
     */
    public function forceDelete(User $user, PromptTemplate $promptTemplate): bool
    {
        // Only admins can permanently delete prompt templates
        return $user->hasPermissionTo('system.admin') && $user->hasRole('admin');
    }
}
