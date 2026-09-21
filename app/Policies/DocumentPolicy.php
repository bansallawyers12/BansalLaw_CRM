<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Staff;
use App\Support\StaffClientVisibility;
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
        return true;
    }

    /**
     * Determine if the user can view the document
     */
    public function view(Authenticatable $user, Document $document): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($this->isCreator($user, $document)) {
            return true;
        }

        // If user is the client or lead associated with this document
        $userId = (int) $user->id;
        if ($userId > 0) {
            if ((int) ($document->client_id ?? 0) === $userId) {
                return true;
            }
            if ((int) ($document->lead_id ?? 0) === $userId) {
                return true;
            }
        }

        // Signer matching the document
        if (! empty($user->email)) {
            if ($document->relationLoaded('signers')) {
                if ($document->signers->contains('email', $user->email)) {
                    return true;
                }
            } elseif ($document->signers()->where('email', $user->email)->exists()) {
                return true;
            }
        }

        // Unattributed documents (no client_id and no lead_id) are accessible to all authenticated staff
        if (empty($document->client_id) && empty($document->lead_id)) {
            return true;
        }

        // Check allocation access via StaffClientVisibility
        return StaffClientVisibility::mayAccessDocument($document, $user);
    }

    /**
     * Determine if the user can create documents
     */
    public function create(Authenticatable $user): bool
    {
        return $user instanceof Staff || (isset($user->role) && in_array((int) $user->role, [1, 2, 12, 13, 14, 17], true));
    }

    /**
     * Determine if the user can update the document
     */
    public function update(Authenticatable $user, Document $document): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($this->isCreator($user, $document)) {
            return true;
        }

        return $this->view($user, $document);
    }

    /**
     * Determine if the user can delete the document
     */
    public function delete(Authenticatable $user, Document $document): bool
    {
        // Signed documents can never be deleted
        if ($document->status === 'signed') {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($this->isCreator($user, $document)) {
            return true;
        }

        if ($this->hasAdminConsolePrivileges($user) && $this->view($user, $document)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can view all documents (admin-only view)
     */
    public function viewAll(Authenticatable $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Determine if the user can send reminders for this document
     */
    public function sendReminder(Authenticatable $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    /**
     * Determine if the user can void this document
     */
    public function void(Authenticatable $user, Document $document): bool
    {
        if ($document->status === 'signed') {
            return false;
        }

        return $this->update($user, $document);
    }

    /**
     * Determine if the user can associate/detach documents
     */
    public function associate(Authenticatable $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    protected function isSuperAdmin(Authenticatable $user): bool
    {
        if ($user instanceof Staff && $user->hasEffectiveSuperAdminPrivileges()) {
            return true;
        }

        return (int) ($user->role ?? 0) === 1;
    }

    protected function isCreator(Authenticatable $user, Document $document): bool
    {
        $userId = (int) $user->id;

        return $userId > 0 && (
            (int) ($document->created_by ?? 0) === $userId
            || (int) ($document->user_id ?? 0) === $userId
        );
    }

    protected function hasAdminConsolePrivileges(Authenticatable $user): bool
    {
        if ($user instanceof Staff && $user->canAccessAdminConsole()) {
            return true;
        }

        $role = (int) ($user->role ?? 0);

        return in_array($role, config('crm.admin_console_role_ids', [1, 12, 17]), true);
    }
}
