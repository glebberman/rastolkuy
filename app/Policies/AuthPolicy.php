<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class AuthPolicy
{
    /**
     * Determine whether the user can register.
     */
    public function register(?User $user): bool
    {
        // Any user (including guests/unauthenticated) can register
        return true;
    }

    /**
     * Determine whether the user can login.
     */
    public function login(?User $user): bool
    {
        // Any user (including guests/unauthenticated) can login
        return true;
    }

    /**
     * Determine whether the user can logout.
     */
    public function logout(User $user): bool
    {
        // Authenticated users can logout
        return $user->hasPermissionTo('auth.refresh-token');
    }

    /**
     * Determine whether the user can view their profile.
     */
    public function user(User $user): bool
    {
        // Authenticated users can view their profile
        return $user->hasPermissionTo('auth.refresh-token');
    }

    /**
     * Determine whether the user can update their profile.
     */
    public function updateUser(User $user): bool
    {
        // Customers and admins can update their profile
        return $user->hasPermissionTo('auth.update-profile');
    }

    /**
     * Determine whether the user can request password reset.
     */
    public function forgotPassword(?User $user): bool
    {
        // Any user (including guests/unauthenticated) can request password reset
        return true;
    }

    /**
     * Determine whether the user can reset password.
     */
    public function resetPassword(?User $user): bool
    {
        // Any user (including guests/unauthenticated) can reset password with valid token
        return true;
    }

    /**
     * Determine whether the user can verify email.
     */
    public function verifyEmail(User $user): bool
    {
        // Authenticated users can verify their email
        return $user->hasPermissionTo('auth.verify-email');
    }

    /**
     * Determine whether the user can resend verification email.
     */
    public function resendVerification(User $user): bool
    {
        // Authenticated users can resend verification
        return $user->hasPermissionTo('auth.verify-email');
    }

    /**
     * Determine whether the user can refresh their token.
     */
    public function refreshToken(User $user): bool
    {
        // Authenticated users can refresh their token
        return $user->hasPermissionTo('auth.refresh-token');
    }
}
