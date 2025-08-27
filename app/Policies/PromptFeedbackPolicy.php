<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PromptFeedback;
use App\Models\User;

class PromptFeedbackPolicy
{
    /**
     * Determine whether the user can view any prompt feedback.
     */
    public function viewAny(User $user): bool
    {
        // Only admins can view prompt feedback lists
        return $user->hasPermissionTo('system.view-logs') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the prompt feedback.
     */
    public function view(User $user, PromptFeedback $promptFeedback): bool
    {
        // Only admins can view prompt feedback
        return $user->hasPermissionTo('system.view-logs') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create prompt feedback.
     */
    public function create(User $user): bool
    {
        // Only admins can create prompt feedback
        return $user->hasPermissionTo('system.admin') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the prompt feedback.
     */
    public function update(User $user, PromptFeedback $promptFeedback): bool
    {
        // Only admins can update prompt feedback
        return $user->hasPermissionTo('system.admin') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the prompt feedback.
     */
    public function delete(User $user, PromptFeedback $promptFeedback): bool
    {
        // Only admins can delete prompt feedback
        return $user->hasPermissionTo('system.admin') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the prompt feedback.
     */
    public function restore(User $user, PromptFeedback $promptFeedback): bool
    {
        // Only admins can restore prompt feedback
        return $user->hasPermissionTo('system.admin') && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the prompt feedback.
     */
    public function forceDelete(User $user, PromptFeedback $promptFeedback): bool
    {
        // Only admins can permanently delete prompt feedback
        return $user->hasPermissionTo('system.admin') && $user->hasRole('admin');
    }
}
