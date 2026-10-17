<?php

namespace App\Services;

use App\Models\ClientMatter;
use App\Models\Staff;
use App\Support\MatterStreamHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the Overview "Matter team & details" card payload from stored matter data only.
 * Does not invent display fallbacks for unassigned staff or office.
 */
final class MatterTeamOverview
{
    /**
     * @return array{
     *     client_matter_id: int,
     *     matter_reference: string|null,
     *     team: list<array{
     *         key: string,
     *         role: string,
     *         role_title: string,
     *         status: 'assigned'|'unassigned'|'missing',
     *         name: string|null,
     *         initials: string|null,
     *         avatar_variant: string,
     *         is_office: bool
     *     }>,
     *     case_details: list<array{label: string, value: string, kind: string}>
     * }|null
     */
    public static function forClient(int $clientId, ?string $matterRef = null): ?array
    {
        if (! Schema::hasTable('client_matters')) {
            return null;
        }

        $matter = self::resolveMatter($clientId, $matterRef);
        if ($matter === null) {
            return null;
        }

        return self::buildFromMatter($matter);
    }

    /**
     * @return array{
     *     client_matter_id: int,
     *     matter_reference: string|null,
     *     team: list<array{
     *         key: string,
     *         role: string,
     *         role_title: string,
     *         status: 'assigned'|'unassigned'|'missing',
     *         name: string|null,
     *         initials: string|null,
     *         avatar_variant: string,
     *         is_office: bool
     *     }>,
     *     case_details: list<array{label: string, value: string, kind: string}>
     * }
     */
    public static function buildFromMatter(ClientMatter $matter): array
    {
        $matter->loadMissing(['legalPractitioner', 'personResponsible', 'personAssisting', 'office', 'matter']);

        $ref = trim((string) ($matter->client_unique_matter_no ?? ''));

        return [
            'client_matter_id' => (int) $matter->id,
            'matter_reference' => $ref !== '' ? $ref : null,
            'team' => [
                self::staffSlot(
                    'legal_practitioner',
                    'Legal practitioner',
                    'Legal Practitioner',
                    'navy',
                    $matter->sel_legal_practitioner ?? null,
                    $matter->legalPractitioner
                ),
                self::staffSlot(
                    'person_responsible',
                    'Person responsible',
                    'Person Responsible',
                    'blue',
                    $matter->sel_person_responsible ?? null,
                    $matter->personResponsible
                ),
                self::staffSlot(
                    'person_assisting',
                    'Person assisting',
                    'Person Assisting',
                    'teal',
                    $matter->sel_person_assisting ?? null,
                    $matter->personAssisting
                ),
                self::officeSlot($matter->office_id ?? null, $matter->office),
            ],
            'case_details' => self::buildCaseDetails($matter),
        ];
    }

    private static function resolveMatter(int $clientId, ?string $matterRef): ?ClientMatter
    {
        $with = ['legalPractitioner', 'personResponsible', 'personAssisting', 'office', 'matter'];

        $ref = $matterRef !== null ? trim($matterRef) : '';
        if ($ref !== '') {
            $byRef = ClientMatter::query()
                ->with($with)
                ->where('client_id', $clientId)
                ->where('client_unique_matter_no', $ref)
                ->first();
            if ($byRef) {
                return $byRef;
            }
        }

        return ClientMatter::query()
            ->with($with)
            ->where('client_id', $clientId)
            ->where('matter_status', 1)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{
     *     key: string,
     *     role: string,
     *     role_title: string,
     *     status: 'assigned'|'unassigned'|'missing',
     *     name: string|null,
     *     initials: string|null,
     *     avatar_variant: string,
     *     is_office: bool
     * }
     */
    private static function staffSlot(
        string $key,
        string $role,
        string $roleTitle,
        string $avatarVariant,
        mixed $rawId,
        ?Staff $staff
    ): array {
        if (self::isUnset($rawId)) {
            return [
                'key' => $key,
                'role' => $role,
                'role_title' => $roleTitle,
                'status' => 'unassigned',
                'name' => null,
                'initials' => null,
                'avatar_variant' => $avatarVariant,
                'is_office' => false,
            ];
        }

        if ($staff === null) {
            return [
                'key' => $key,
                'role' => $role,
                'role_title' => $roleTitle,
                'status' => 'missing',
                'name' => null,
                'initials' => null,
                'avatar_variant' => $avatarVariant,
                'is_office' => false,
            ];
        }

        $first = trim((string) ($staff->first_name ?? ''));
        $last = trim((string) ($staff->last_name ?? ''));
        $name = trim($first . ' ' . $last);

        return [
            'key' => $key,
            'role' => $role,
            'role_title' => $roleTitle,
            'status' => 'assigned',
            'name' => $name !== '' ? $name : null,
            'initials' => self::initials($first, $last),
            'avatar_variant' => $avatarVariant,
            'is_office' => false,
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     role: string,
     *     role_title: string,
     *     status: 'assigned'|'unassigned'|'missing',
     *     name: string|null,
     *     initials: string|null,
     *     avatar_variant: string,
     *     is_office: bool
     * }
     */
    private static function officeSlot(mixed $rawId, mixed $office): array
    {
        if (self::isUnset($rawId)) {
            return [
                'key' => 'office',
                'role' => 'Handling office',
                'role_title' => 'Handling Office',
                'status' => 'unassigned',
                'name' => null,
                'initials' => null,
                'avatar_variant' => 'slate',
                'is_office' => true,
            ];
        }

        $officeName = $office ? trim((string) ($office->office_name ?? '')) : '';
        if ($officeName === '') {
            return [
                'key' => 'office',
                'role' => 'Handling office',
                'role_title' => 'Handling Office',
                'status' => 'missing',
                'name' => null,
                'initials' => null,
                'avatar_variant' => 'slate',
                'is_office' => true,
            ];
        }

        return [
            'key' => 'office',
            'role' => 'Handling office',
            'role_title' => 'Handling Office',
            'status' => 'assigned',
            'name' => $officeName,
            'initials' => null,
            'avatar_variant' => 'slate',
            'is_office' => true,
        ];
    }

    /**
     * @return list<array{label: string, value: string, kind: string}>
     */
    private static function buildCaseDetails(ClientMatter $matter): array
    {
        $rows = [];

        if (Schema::hasColumn('client_matters', 'our_party_role')) {
            $ourRoleVal = trim((string) ($matter->our_party_role ?? ''));
            if ($ourRoleVal !== '') {
                $stream = 'general';
                if ($matter->matter && ! empty($matter->matter->stream)) {
                    $stream = (string) $matter->matter->stream;
                }
                $roleLabels = MatterStreamHelper::partyRolesForStream($stream);
                $rows[] = [
                    'label' => "Our client's role",
                    'value' => $roleLabels[$ourRoleVal] ?? $ourRoleVal,
                    'kind' => 'role',
                ];
            }
        }

        if (Schema::hasColumn('client_matters', 'incidence_type')) {
            $subtype = trim((string) ($matter->incidence_type ?? ''));
            if ($subtype !== '') {
                $rows[] = [
                    'label' => 'Matter subtype',
                    'value' => $subtype,
                    'kind' => 'text',
                ];
            }
        }

        if (Schema::hasColumn('client_matters', 'date_of_incidence') && ! empty($matter->date_of_incidence)) {
            try {
                $doiLabel = Carbon::parse($matter->date_of_incidence)->format('d/m/Y');
            } catch (\Throwable $e) {
                $doiLabel = (string) $matter->date_of_incidence;
            }
            $rows[] = [
                'label' => 'Date of incidence',
                'value' => $doiLabel,
                'kind' => 'text',
            ];
        }

        if (Schema::hasColumn('client_matters', 'case_detail')) {
            $rawCaseDetail = trim((string) ($matter->case_detail ?? ''));
            if ($rawCaseDetail !== '') {
                $rows[] = [
                    'label' => 'Case detail',
                    'value' => $rawCaseDetail,
                    'kind' => 'full',
                ];
            }
        }

        return $rows;
    }

    private static function isUnset(mixed $value): bool
    {
        return $value === null || $value === '' || $value === 0 || $value === '0';
    }

    private static function initials(string $first, string $last): ?string
    {
        $a = $first !== '' ? mb_strtoupper(mb_substr($first, 0, 1)) : '';
        $b = $last !== '' ? mb_strtoupper(mb_substr($last, 0, 1)) : '';
        $combined = $a . $b;

        return $combined !== '' ? $combined : null;
    }
}
