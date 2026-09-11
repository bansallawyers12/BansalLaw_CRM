<?php

namespace App\Services\Email;

use App\Models\EmailLabel;
use Illuminate\Support\Collection;

/**
 * Single catalog for email labels used by CRM mail UI and Admin Console CRUD.
 *
 * Admin Console owns create/edit of labels; CRM only lists active labels and
 * applies/removes them on email logs via /email-labels/*.
 */
class EmailLabelCatalogService
{
    /**
     * Active system labels plus the staff member's active custom labels.
     * This is the set shown in CRM filter/apply UI.
     */
    public function listVisibleForStaff(?int $staffId): Collection
    {
        return EmailLabel::query()
            ->where(function ($query) use ($staffId) {
                $query->whereNull('user_id');
                if ($staffId) {
                    $query->orWhere('user_id', $staffId);
                }
            })
            ->active()
            ->orderByDesc('type')
            ->orderBy('name')
            ->get();
    }
}
