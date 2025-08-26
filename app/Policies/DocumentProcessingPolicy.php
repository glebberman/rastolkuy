<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentProcessing;
use App\Models\User;

class DocumentProcessingPolicy
{
    /**
     * Determine whether the user can view any document processings.
     */
    public function viewAny(User $user): bool
    {
        // Only admins can view all documents
        return $user->hasPermissionTo('documents.view-admin');
    }

    /**
     * Determine whether the user can view the document processing.
     */
    public function view(User $user, DocumentProcessing $documentProcessing): bool
    {
        // Users can view their own documents, admins can view any
        return $user->hasPermissionTo('documents.view')
               && ($user->hasPermissionTo('documents.view-admin') || $this->isOwner($user, $documentProcessing));
    }

    /**
     * Determine whether the user can create document processings.
     */
    public function create(User $user): bool
    {
        // Customers and admins can create documents
        return $user->hasPermissionTo('documents.create');
    }

    /**
     * Determine whether the user can update the document processing.
     */
    public function update(User $user, DocumentProcessing $documentProcessing): bool
    {
        // Users can update their own documents (if not completed), admins can update any
        return $user->hasPermissionTo('documents.update')
               && ($user->hasPermissionTo('documents.view-admin')
                || ($this->isOwner($user, $documentProcessing)
                 && $documentProcessing->status !== DocumentProcessing::STATUS_COMPLETED));
    }

    /**
     * Determine whether the user can delete the document processing.
     */
    public function delete(User $user, DocumentProcessing $documentProcessing): bool
    {
        // Users can delete their own documents, admins can delete any
        return $user->hasPermissionTo('documents.delete')
               && ($user->hasPermissionTo('documents.view-admin') || $this->isOwner($user, $documentProcessing));
    }

    /**
     * Determine whether the user can restore the document processing.
     */
    public function restore(User $user, DocumentProcessing $documentProcessing): bool
    {
        // Only admins can restore deleted documents
        return $user->hasPermissionTo('system.admin');
    }

    /**
     * Determine whether the user can permanently delete the document processing.
     */
    public function forceDelete(User $user, DocumentProcessing $documentProcessing): bool
    {
        // Only admins can permanently delete documents
        return $user->hasPermissionTo('system.admin');
    }

    /**
     * Determine whether the user can process documents.
     */
    public function process(User $user, DocumentProcessing $documentProcessing): bool
    {
        // Users can process their own documents, admins can process any
        return $user->hasPermissionTo('documents.process')
               && ($user->hasPermissionTo('documents.view-admin') || $this->isOwner($user, $documentProcessing));
    }

    /**
     * Determine whether the user can cancel document processing.
     */
    public function cancel(User $user, DocumentProcessing $documentProcessing): bool
    {
        // Users can cancel their own documents (if processing), admins can cancel any
        return $user->hasPermissionTo('documents.cancel')
               && ($user->hasPermissionTo('documents.view-admin')
                || ($this->isOwner($user, $documentProcessing)
                 && in_array($documentProcessing->status, [DocumentProcessing::STATUS_PENDING, DocumentProcessing::STATUS_PROCESSING], true)));
    }

    /**
     * Determine whether the user can view statistics.
     */
    public function stats(User $user): bool
    {
        // Only admins can view statistics
        return $user->hasPermissionTo('documents.stats');
    }

    /**
     * Check if the user is the owner of the document processing.
     */
    private function isOwner(User $user, DocumentProcessing $documentProcessing): bool
    {
        return $user->id === $documentProcessing->user_id;
    }
}
