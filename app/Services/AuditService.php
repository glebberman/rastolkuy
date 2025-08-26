<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuditService
{
    /**
     * Логирует критичные действия администратора.
     */
    public function logAdminAction(User $admin, string $action, array $context = []): void
    {
        if (!$admin->hasRole('admin')) {
            return;
        }

        Log::channel('audit')->info('Admin action performed', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'action' => $action,
            'context' => $context,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Логирует доступ к данным пользователя.
     */
    public function logUserDataAccess(User $accessor, User $targetUser, string $action): void
    {
        // Логируем только если это не сам пользователь
        if ($accessor->id === $targetUser->id) {
            return;
        }

        Log::channel('audit')->info('User data accessed', [
            'accessor_id' => $accessor->id,
            'accessor_email' => $accessor->email,
            'target_user_id' => $targetUser->id,
            'target_user_email' => $targetUser->email,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Логирует доступ к документам.
     */
    public function logDocumentAccess(User $user, string $documentUuid, string $action): void
    {
        Log::channel('audit')->info('Document accessed', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'document_uuid' => $documentUuid,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Логирует критичные действия с документами.
     */
    public function logCriticalDocumentAction(User $user, string $documentUuid, string $action, array $context = []): void
    {
        Log::channel('audit')->warning('Critical document action', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'document_uuid' => $documentUuid,
            'action' => $action,
            'context' => $context,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Логирует неавторизованные попытки доступа.
     */
    public function logUnauthorizedAccess(string $resource, string $action, ?User $user = null): void
    {
        Log::channel('security')->warning('Unauthorized access attempt', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'resource' => $resource,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
