<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

readonly class AuthService
{
    public function __construct(
        private CreditService $creditService,
    ) {
    }

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): User
    {
        $validated = $request->validated();

        if (!is_string($validated['name']) || !is_string($validated['email']) || !is_string($validated['password'])) {
            throw new InvalidArgumentException('Invalid input data');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Assign default customer role to new users
        $user->assignRole('customer');

        // Send email verification
        event(new Registered($user));

        return $user;
    }

    /**
     * Authenticate user and return token.
     *
     * @throws ValidationException
     *
     * @return array{user: User, token: string}
     */
    public function login(LoginRequest $request): array
    {
        $credentials = $request->only(['email', 'password']);

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль.'],
            ]);
        }

        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Authentication failed.'],
            ]);
        }

        // Delete existing tokens for security
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Update user profile.
     */
    public function updateUser(UpdateUserRequest $request): User
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user) {
            throw new RuntimeException('Unauthenticated user');
        }

        $validated = $request->validated();

        if (isset($validated['name'])) {
            if (!is_string($validated['name'])) {
                throw new InvalidArgumentException('Name must be a string');
            }
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            if (!is_string($validated['email'])) {
                throw new InvalidArgumentException('Email must be a string');
            }
            $user->email = $validated['email'];
            $user->email_verified_at = null; // Require re-verification
        }

        if (isset($validated['password'])) {
            if (!is_string($validated['password'])) {
                throw new InvalidArgumentException('Password must be a string');
            }
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Send verification email if email changed
        if (isset($validated['email'])) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }

    /**
     * Send password reset email.
     */
    public function sendPasswordResetEmail(ForgotPasswordRequest $request): void
    {
        $status = Password::sendResetLink(
            $request->only('email'),
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => ['Не удалось отправить письмо для сброса пароля.'],
            ]);
        }
    }

    /**
     * Reset user password.
     */
    public function resetPassword(ResetPasswordRequest $request): void
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->password = Hash::make($password);
                $user->save();

                // Delete all tokens for security
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['Недействительный или истекший токен сброса пароля.'],
            ]);
        }
    }

    /**
     * Change user password.
     */
    public function changePassword(User $user, ChangePasswordRequest $request): void
    {
        $validated = $request->validated();

        // Verify current password
        $currentPassword = $validated['current_password'];
        if (!is_string($currentPassword)) {
            throw ValidationException::withMessages([
                'current_password' => ['Неверный формат текущего пароля.'],
            ]);
        }

        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Неверный текущий пароль.'],
            ]);
        }

        // Update password
        $newPassword = $validated['password'];
        if (!is_string($newPassword)) {
            throw ValidationException::withMessages([
                'password' => ['Неверный формат нового пароля.'],
            ]);
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        // Optionally, delete all tokens for security
        // $user->tokens()->delete();
    }

    /**
     * Refresh user token.
     *
     * @return array{token: string}
     */
    public function refreshToken(User $user): array
    {
        // Delete current token
        if ($user->currentAccessToken() !== null) {
            $user->currentAccessToken()->delete();
        }

        // Create new token
        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'token' => $token,
        ];
    }

    /**
     * Get user statistics with caching.
     *
     * @return array{credits_balance: float, total_documents: int, processed_today: int, last_activity: string}
     */
    public function getUserStats(User $user): array
    {
        $cacheKey = "user_stats_{$user->id}";

        /** @var array{credits_balance: float, total_documents: int, processed_today: int, last_activity: string} */
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user): array {
            // Get credits balance
            $creditsBalance = $this->creditService->getBalance($user);

            // Get document statistics
            $totalDocuments = $user->documentProcessings()->count();

            $processedToday = $user->documentProcessings()
                ->whereIn('status', ['completed', 'failed'])
                ->whereDate('completed_at', Carbon::today())
                ->count();

            // Get last activity
            $lastDocument = $user->documentProcessings()
                ->latest('updated_at')
                ->first();

            if ($lastDocument !== null && $lastDocument->updated_at !== null) {
                $lastActivity = $lastDocument->updated_at->toISOString();
            } elseif ($user->updated_at !== null) {
                $lastActivity = $user->updated_at->toISOString();
            } else {
                $lastActivity = now()->toISOString();
            }

            return [
                'credits_balance' => $creditsBalance,
                'total_documents' => $totalDocuments,
                'processed_today' => $processedToday,
                'last_activity' => $lastActivity,
            ];
        });
    }
}
