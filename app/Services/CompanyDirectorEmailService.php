<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientEmail;
use App\Models\Company;
use App\Models\CompanyDirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class CompanyDirectorEmailService
{
    public const SHARED_EMAIL_TYPE = 'Company (shared)';

    /**
     * Internal placeholder domains used when admins.email must be unique.
     */
    public function isPlaceholderEmail(?string $email): bool
    {
        if ($email === null || trim($email) === '') {
            return true;
        }

        $email = strtolower(trim($email));

        return str_ends_with($email, '@lead.internal')
            || str_contains($email, '@lead.internal');
    }

    /**
     * Primary contact email for a company client/lead (from client_emails, then admins).
     */
    public function resolveCompanyPrimaryEmail(Admin $companyClient): ?string
    {
        $emails = ClientEmail::where('client_id', $companyClient->id)
            ->orderByRaw("CASE WHEN email_type IN ('Work', 'Business', 'Company') THEN 0 WHEN email_type = 'Personal' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->get();

        foreach ($emails as $row) {
            if (! empty($row->email) && ! $this->isPlaceholderEmail($row->email)) {
                return trim($row->email);
            }
        }

        if (Schema::hasColumn('admins', 'email') && ! empty($companyClient->email) && ! $this->isPlaceholderEmail($companyClient->email)) {
            return trim($companyClient->email);
        }

        return null;
    }

    /**
     * Director person ids linked to this company.
     *
     * @return list<int>
     */
    public function directorPersonIdsForCompany(int $companyAdminId): array
    {
        $company = Company::where('admin_id', $companyAdminId)->first();
        if (! $company) {
            return [];
        }

        return CompanyDirector::where('company_id', $company->id)
            ->whereNotNull('director_client_id')
            ->pluck('director_client_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Client ids allowed to hold the same address as the company (company + its directors).
     *
     * @return list<int>
     */
    public function companyEmailShareGroupIds(int $companyAdminId): array
    {
        return array_values(array_unique(array_merge(
            [$companyAdminId],
            $this->directorPersonIdsForCompany($companyAdminId)
        )));
    }

    /**
     * Whether this email may be reused for a director of the given company.
     */
    public function canReuseEmailForCompanyDirector(string $email, int $companyAdminId, ?int $excludeClientId = null): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        $companyClient = Admin::find($companyAdminId);
        if (! $companyClient) {
            return false;
        }

        $onCompany = ClientEmail::where('client_id', $companyAdminId)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        if (! $onCompany && ! empty($companyClient->email)
            && strtolower(trim($companyClient->email)) === $email
            && ! $this->isPlaceholderEmail($companyClient->email)) {
            $onCompany = true;
        }

        if (! $onCompany) {
            return false;
        }

        return ! $this->emailExistsOutsideShareGroup($email, $companyAdminId, $excludeClientId);
    }

    /**
     * Whether email is blocked for a new personal director record.
     */
    public function isEmailTakenByUnrelatedClient(string $email, int $companyAdminId, ?int $excludeClientId = null): bool
    {
        return $this->emailExistsOutsideShareGroup($email, $companyAdminId, $excludeClientId);
    }

    public function emailExistsOutsideShareGroup(string $email, int $companyAdminId, ?int $excludeClientId = null): bool
    {
        $email = strtolower(trim($email));
        $allowedIds = $this->companyEmailShareGroupIds($companyAdminId);
        if ($excludeClientId) {
            $allowedIds[] = $excludeClientId;
        }

        if (Schema::hasColumn('admins', 'email')) {
            $adminHit = Admin::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($adminHit && ! in_array((int) $adminHit->id, $allowedIds, true)) {
                return true;
            }
        }

        $clientEmailHit = ClientEmail::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($clientEmailHit && ! in_array((int) $clientEmailHit->client_id, $allowedIds, true)) {
            return true;
        }

        return false;
    }

    public function allocateUniqueAdminEmail(string $prefix): string
    {
        $slug = preg_replace('/[^a-z0-9]/i', '_', substr($prefix, 0, 40));
        $slug = $slug !== '' ? $slug : 'director';

        return 'director_' . $slug . '_' . time() . '@lead.internal';
    }

    /**
     * Create a searchable director person (lead/client) with company or personal email.
     */
    public function createDirectorPerson(
        Admin $companyClient,
        string $firstName,
        string $lastName,
        string $mode,
        ?string $personalEmail = null,
    ): Admin {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        if ($firstName === '' && $lastName === '') {
            throw new \InvalidArgumentException('Director name is required.');
        }

        $mode = strtolower(trim($mode));
        if (! in_array($mode, ['company_email', 'personal'], true)) {
            throw new \InvalidArgumentException('Invalid director email mode.');
        }

        $contactEmail = null;
        $adminEmail = null;
        $isShared = false;

        if ($mode === 'company_email') {
            $contactEmail = $this->resolveCompanyPrimaryEmail($companyClient);
            if (! $contactEmail) {
                throw new \InvalidArgumentException('Company has no email on file. Add a company email first, or enter a personal email for this director.');
            }
            if (! $this->canReuseEmailForCompanyDirector($contactEmail, (int) $companyClient->id)) {
                throw new \InvalidArgumentException('Company email is already registered to another client.');
            }
            $adminEmail = $this->allocateUniqueAdminEmail($firstName . '_' . $lastName);
            $isShared = true;
        } else {
            $contactEmail = strtolower(trim((string) $personalEmail));
            if ($contactEmail === '' || ! filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('A valid personal email is required.');
            }
            if ($this->isEmailTakenByUnrelatedClient($contactEmail, (int) $companyClient->id)) {
                throw new \InvalidArgumentException('This email is already registered for another client.');
            }
            $companyPrimary = $this->resolveCompanyPrimaryEmail($companyClient);
            $isShared = $companyPrimary && strtolower($companyPrimary) === $contactEmail;
            $adminEmail = $contactEmail;
            if (Schema::hasColumn('admins', 'email')
                && Admin::whereRaw('LOWER(email) = ?', [$contactEmail])->exists()) {
                $adminEmail = $this->allocateUniqueAdminEmail($firstName . '_' . $lastName);
            }
        }

        $referenceService = app(ClientReferenceService::class);

        return DB::transaction(function () use ($companyClient, $firstName, $lastName, $adminEmail, $contactEmail, $isShared, $referenceService) {
            $reference = $referenceService->generateClientReference($firstName !== '' ? $firstName : $lastName);

            $adminData = [
                'password' => Hash::make('LEAD_PLACEHOLDER'),
                'client_counter' => $reference['client_counter'],
                'client_id' => $reference['client_id'],
                'status' => 1,
                'lead_status' => $companyClient->type === 'lead' ? ($companyClient->lead_status ?? 'new') : null,
                'followup_date' => null,
                'type' => $companyClient->type === 'client' ? 'client' : 'lead',
                'is_archived' => 0,
                'is_deleted' => null,
                'is_company' => 0,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => null,
                'email' => $adminEmail,
                'email_type' => $isShared ? self::SHARED_EMAIL_TYPE : 'Personal',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('admins', 'is_other_party')) {
                $adminData['is_other_party'] = (int) ($companyClient->is_other_party ?? 0);
            }

            $this->applyAssigneeFromCompany($adminData, $companyClient);
            $this->pruneAdminInsertData($adminData);

            $adminId = DB::table('admins')->insertGetId($adminData);
            $person = Admin::findOrFail($adminId);

            $emailPayload = [
                'admin_id' => Auth::id(),
                'client_id' => $person->id,
                'email_type' => $isShared ? self::SHARED_EMAIL_TYPE : 'Personal',
                'email' => $contactEmail,
                'is_verified' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('client_emails', 'is_shared_company_email')) {
                $emailPayload['is_shared_company_email'] = $isShared;
            }
            ClientEmail::create($emailPayload);

            return $person;
        });
    }

    /**
     * Resolve display email for a linked director person.
     */
    public function resolveDirectorDisplayEmail(?Admin $directorClient, int $companyAdminId): ?array
    {
        if (! $directorClient) {
            return null;
        }

        $rows = ClientEmail::where('client_id', $directorClient->id)->orderBy('id')->get();
        foreach ($rows as $row) {
            if (! empty($row->email) && ! $this->isPlaceholderEmail($row->email)) {
                $shared = (bool) ($row->is_shared_company_email ?? false)
                    || $row->email_type === self::SHARED_EMAIL_TYPE;

                return [
                    'email' => $row->email,
                    'is_shared' => $shared,
                ];
            }
        }

        if (! empty($directorClient->email) && ! $this->isPlaceholderEmail($directorClient->email)) {
            return [
                'email' => $directorClient->email,
                'is_shared' => false,
            ];
        }

        return null;
    }

    private function applyAssigneeFromCompany(array &$adminData, Admin $companyClient): void
    {
        $assignUserId = Auth::id();
        if (Schema::hasColumn('admins', 'user_id') && ! empty($companyClient->user_id)) {
            $adminData['user_id'] = (int) $companyClient->user_id;
        } elseif (Schema::hasColumn('admins', 'agent_id') && ! empty($companyClient->agent_id)) {
            $adminData['agent_id'] = (int) $companyClient->agent_id;
        } elseif (Schema::hasColumn('admins', 'user_id')) {
            $adminData['user_id'] = $assignUserId;
        } elseif (Schema::hasColumn('admins', 'agent_id')) {
            $adminData['agent_id'] = $assignUserId;
        }
    }

    private function pruneAdminInsertData(array &$adminData): void
    {
        foreach (array_keys($adminData) as $col) {
            if (! Schema::hasColumn('admins', $col)) {
                unset($adminData[$col]);
            }
        }
    }
}
