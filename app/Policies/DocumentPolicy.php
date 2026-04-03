<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class DocumentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any documents
     */
    public function viewAny(Authenticatable $user): bool
    {
        // All authenticated CRM users (Staff) can view documents
        return true;
    }

    /**
     * Determine if the user can view the document
     */
    public function view(Authenticatable $user, Document $document): bool
    {
        // Global access - everyone can view all documents
        return true;
    }

    /**
     * Determine if the user can create documents
     */
    public function create(Authenticatable $user): bool
    {
        // Only Staff can create documents (clients use Admin model, no document creation in CRM)
        return $user instanceof Staff;
    }

    /**
     * Determine if the user can update the document
     */
    public function update(Authenticatable $user, Document $document): bool
    {
        // Global access - everyone can update all documents
        return true;
    }

    /**
     * Determine if the user can delete the document
     */
    public function delete(Authenticatable $user, Document $document): bool
    {
        // Global access - everyone can delete documents (only if not signed)
        return $document->status !== 'signed';
    }

    /**
     * Determine if the user can view all documents (admin-only view)
     */
    public function viewAll(Authenticatable $user): bool
    {
        // Only super admins can view all documents
        return $user->role === 1;
    }

    /**
     * Determine if the user can send reminders for this document
     */
    public function sendReminder(Authenticatable $user, Document $document): bool
    {
        // Global access - everyone can send reminders for any document
        return true;
    }

    /**
     * Determine if the user can void this document
     */
    public function void(Authenticatable $user, Document $document): bool
    {
        // Global access - everyone can void documents (only if not signed)
        return $document->status !== 'signed';
    }

    /**
     * Determine if the user can associate/detach documents
     */
    public function associate(Authenticatable $user, Document $document): bool
    {
        // Global access - everyone can associate/detach documents
        return true;
    }
}

