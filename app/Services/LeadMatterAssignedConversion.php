<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Lead;

/**
 * Convert a lead to a client once they have at least one active matter with a law matter type assigned.
 * Mirrors the "assigned matter" rule used on lead detail and conversion flows.
 */
final class LeadMatterAssignedConversion
{
    /**
     * No-op if the admins row is not a lead, or the active-assigned-matter condition is not met.
     */
    public static function applyForAdminId(int $adminId): void
    {
        if (! ClientMatter::clientHasActiveAssignedMatter($adminId)) {
            return;
        }

        $admin = Admin::query()->find($adminId);
        if (! $admin || ! self::adminRowIsLead($admin)) {
            return;
        }

        $lead = Lead::withArchived()->find($adminId);
        if (! $lead) {
            return;
        }

        $lead->convertToClient();
    }

    private static function adminRowIsLead(Admin $admin): bool
    {
        if ((int) $admin->type === 1) {
            return true;
        }
        $raw = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', (string) ($admin->type ?? ''));
        $t = mb_strtolower(trim($raw), 'UTF-8');

        return in_array($t, Lead::LEAD_TYPE_VALUES, true);
    }
}
