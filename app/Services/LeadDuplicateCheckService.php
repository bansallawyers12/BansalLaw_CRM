<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use Illuminate\Support\Facades\DB;

class LeadDuplicateCheckService
{
    /**
     * @return array{match: string, value: string, admin_id: int|null}|null
     */
    public function findDuplicate(?string $email, ?string $phone): ?array
    {
        if ($this->isCheckableEmail($email)) {
            $admin = $this->findAdminByEmail($email);
            if ($admin) {
                return [
                    'match' => 'email',
                    'value' => trim((string) $email),
                    'admin_id' => (int) $admin->id,
                ];
            }
        }

        $normalizedPhone = $this->normalizePhoneDigits($phone);
        if ($normalizedPhone !== '') {
            $admin = $this->findAdminByPhone($normalizedPhone);
            if ($admin) {
                return [
                    'match' => 'phone',
                    'value' => trim((string) $phone),
                    'admin_id' => (int) $admin->id,
                ];
            }
        }

        return null;
    }

    public function isDuplicate(?string $email, ?string $phone): bool
    {
        return $this->findDuplicate($email, $phone) !== null;
    }

    public function duplicateMessage(?string $email, ?string $phone): string
    {
        $duplicate = $this->findDuplicate($email, $phone);
        if ($duplicate === null) {
            return 'Lead with same email or phone already exists.';
        }

        return 'Lead with same ' . $duplicate['match'] . ' (' . $duplicate['value'] . ') already exists.';
    }

    public function isCheckableEmail(?string $email): bool
    {
        $email = strtolower(trim((string) $email));

        return $email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL)
            && ! str_contains($email, '@lead.internal');
    }

    public function normalizePhoneDigits(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '';
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 10 && $digits[0] === '0') {
            return '61' . substr($digits, 1);
        }

        if (strlen($digits) === 9 && $digits[0] === '4') {
            return '61' . $digits;
        }

        return $digits;
    }

    private function findAdminByEmail(string $email): ?Admin
    {
        $lower = strtolower(trim($email));

        $admin = Admin::query()
            ->whereIn('type', ['client', 'lead'])
            ->whereNull('is_deleted')
            ->whereRaw('LOWER(email) = ?', [$lower])
            ->first();

        if ($admin) {
            return $admin;
        }

        $clientEmail = ClientEmail::query()
            ->whereRaw('LOWER(email) = ?', [$lower])
            ->first();

        if (! $clientEmail) {
            return null;
        }

        return Admin::query()
            ->whereIn('type', ['client', 'lead'])
            ->whereNull('is_deleted')
            ->where('id', $clientEmail->client_id)
            ->first();
    }

    private function findAdminByPhone(string $normalizedPhone): ?Admin
    {
        $admin = Admin::query()
            ->whereIn('type', ['client', 'lead'])
            ->whereNull('is_deleted')
            ->where(function ($query) use ($normalizedPhone) {
                $this->applyNormalizedPhoneMatch($query, 'phone', $normalizedPhone);
            })
            ->first();

        if ($admin) {
            return $admin;
        }

        $contact = ClientContact::query()
            ->where(function ($query) use ($normalizedPhone) {
                $this->applyNormalizedPhoneMatch($query, 'phone', $normalizedPhone);
            })
            ->first();

        if (! $contact) {
            return null;
        }

        return Admin::query()
            ->whereIn('type', ['client', 'lead'])
            ->whereNull('is_deleted')
            ->where('id', $contact->client_id)
            ->first();
    }

    private function applyNormalizedPhoneMatch($query, string $column, string $normalizedPhone): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $query->whereRaw(
                "REGEXP_REPLACE(COALESCE({$column}, ''), '[^0-9]', '', 'g') = ?",
                [$normalizedPhone]
            );

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $query->whereRaw(
                "REGEXP_REPLACE(COALESCE({$column}, ''), '[^0-9]', '') = ?",
                [$normalizedPhone]
            );

            return;
        }

        $query->where($column, 'like', '%' . $normalizedPhone . '%');
    }
}
