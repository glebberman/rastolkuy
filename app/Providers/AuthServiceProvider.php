<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\DocumentProcessing;
use App\Models\User;
use App\Policies\AuthPolicy;
use App\Policies\DocumentProcessingPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        DocumentProcessing::class => DocumentProcessingPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Register custom gates for auth controller
        Gate::define('auth.register', [AuthPolicy::class, 'register']);
        Gate::define('auth.login', [AuthPolicy::class, 'login']);
        Gate::define('auth.logout', [AuthPolicy::class, 'logout']);
        Gate::define('auth.user', [AuthPolicy::class, 'user']);
        Gate::define('auth.updateUser', [AuthPolicy::class, 'updateUser']);
        Gate::define('auth.forgotPassword', [AuthPolicy::class, 'forgotPassword']);
        Gate::define('auth.resetPassword', [AuthPolicy::class, 'resetPassword']);
        Gate::define('auth.changePassword', [AuthPolicy::class, 'changePassword']);
        Gate::define('auth.verifyEmail', [AuthPolicy::class, 'verifyEmail']);
        Gate::define('auth.resendVerification', [AuthPolicy::class, 'resendVerification']);
        Gate::define('auth.refreshToken', [AuthPolicy::class, 'refreshToken']);
        Gate::define('auth.stats', [AuthPolicy::class, 'stats']);

        // Register admin super-user gate
        Gate::before(function (?User $user, string $ability) {
            // Only apply for authenticated admin users
            return $user && $user->hasRole('admin') ? true : null;
        });
    }
}
