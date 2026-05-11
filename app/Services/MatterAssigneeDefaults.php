<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ClientMatter;
use App\Models\Staff;

/**
 * Persists default matter assignees when a new ClientMatter is created without
 * office/staff selections — aligns with display fallbacks in
 * resources/views/crm/clients/tabs/personal_details.blade.php (Matter assignee card).
 */
final class MatterAssigneeDefaults
{
    public static function applyMissingToNewMatter(ClientMatter $row): void
    {
        if (self::isUnset($row->office_id)) {
            $id = self::melbourneOfficeId();
            if ($id !== null) {
                $row->office_id = $id;
            }
        }
        if (self::isUnset($row->sel_legal_practitioner)) {
            $id = self::staffIdByFirstLast(['Ajay'], 'Bansal');
            if ($id !== null) {
                $row->sel_legal_practitioner = $id;
            }
        }
        if (self::isUnset($row->sel_person_responsible)) {
            $id = self::staffIdByWorkEmail(ClientEditService::DEFAULT_PERSON_RESPONSIBLE_EMAIL)
                ?? self::staffIdByFirstLast(['Michael'], 'Saleh');
            if ($id !== null) {
                $row->sel_person_responsible = $id;
            }
        }
        if (self::isUnset($row->sel_person_assisting)) {
            $id = self::staffIdByWorkEmail(ClientEditService::DEFAULT_PERSON_ASSISTING_EMAIL)
                ?? self::staffIdByFirstLast(['Khushi'], 'Sangroya');
            if ($id !== null) {
                $row->sel_person_assisting = $id;
            }
        }
    }

    private static function isUnset($value): bool
    {
        return $value === null || $value === '' || $value === 0 || $value === '0';
    }

    private static function melbourneOfficeId(): ?int
    {
        $id = Branch::query()
            ->whereRaw('LOWER(TRIM(office_name)) = ?', ['melbourne'])
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Match active staff by first/last name (case-insensitive), first name may have variants.
     */
    private static function staffIdByFirstLast(array $firstNameVariants, string $lastName): ?int
    {
        $lastNorm = strtolower(trim($lastName));
        $firstNorms = array_map(static fn ($n) => strtolower(trim((string) $n)), $firstNameVariants);

        $id = Staff::query()
            ->where('status', 1)
            ->whereRaw('LOWER(TRIM(last_name)) = ?', [$lastNorm])
            ->where(function ($q) use ($firstNorms) {
                foreach ($firstNorms as $fn) {
                    $q->orWhereRaw('LOWER(TRIM(first_name)) = ?', [$fn]);
                }
            })
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private static function staffIdByWorkEmail(string $email): ?int
    {
        $norm = strtolower(trim($email));
        if ($norm === '') {
            return null;
        }
        $id = Staff::query()
            ->where('status', 1)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$norm])
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
