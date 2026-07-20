<?php

namespace App\Services;

use App\Models\Email;
use App\Models\Staff;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the emails table (Zoho SMTP / compose senders) in sync with staff records.
 */
class StaffMailboxService
{
    public function syncFromStaff(Staff $staff, ?string $zohoAppPassword = null, ?string $previousEmail = null): ?Email
    {
        if (! Schema::hasTable('emails')) {
            return null;
        }

        $email = strtolower(trim((string) $staff->email));
        if ($email === '') {
            return null;
        }

        $account = $this->findMailboxForStaff($staff, $previousEmail) ?? new Email;
        $account->email = $email;
        $account->display_name = trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')) ?: $email;
        $account->mail_provider = 'zoho';

        if (Schema::hasColumn('emails', 'smtp_host')) {
            $account->smtp_host = $account->smtp_host ?: 'smtp.zoho.com';
            $account->smtp_port = (int) ($account->smtp_port ?: 587);
            $account->smtp_encryption = $account->smtp_encryption ?: 'tls';
        }

        if (Schema::hasColumn('emails', 'sync_enabled')) {
            $account->sync_enabled = (int) ($account->sync_enabled ?? 1);
        }

        if (Schema::hasColumn('emails', 'sync_sent_enabled')) {
            $account->sync_sent_enabled = (int) ($account->sync_sent_enabled ?? 0);
        }

        $account->status = (int) ($staff->status ?? 1) === 1;

        $userIds = $account->exists ? $account->sharedStaffIds() : [];
        $staffId = (int) $staff->id;
        if ($staffId > 0 && ! in_array($staffId, $userIds, true)) {
            $userIds[] = $staffId;
        }
        $account->user_id = json_encode(array_values(array_unique($userIds)));

        if ($zohoAppPassword !== null && trim($zohoAppPassword) !== '' && Schema::hasColumn('emails', 'password')) {
            $account->password = $this->encryptMailboxPassword($zohoAppPassword);
        }

        $account->save();

        return $account;
    }

    public function mailboxPasswordConfigured(Staff $staff): bool
    {
        if (! Schema::hasTable('emails') || ! Schema::hasColumn('emails', 'password')) {
            return false;
        }

        $account = Email::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim((string) $staff->email))])
            ->first();

        return $account && ! blank($account->password);
    }

    public function encryptMailboxPassword(string $password): string
    {
        $password = trim($password);
        if ($password === '') {
            return '';
        }

        try {
            decrypt($password);

            return $password;
        } catch (\Throwable) {
            return encrypt($password);
        }
    }

    protected function findMailboxForStaff(Staff $staff, ?string $previousEmail): ?Email
    {
        $email = strtolower(trim((string) $staff->email));

        $byEmail = Email::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();
        if ($byEmail) {
            return $byEmail;
        }

        $previousEmail = strtolower(trim((string) $previousEmail));
        if ($previousEmail !== '' && $previousEmail !== $email) {
            $byPrevious = Email::query()
                ->whereRaw('LOWER(TRIM(email)) = ?', [$previousEmail])
                ->first();
            if ($byPrevious) {
                return $byPrevious;
            }
        }

        $staffId = (int) $staff->id;
        if ($staffId <= 0) {
            return null;
        }

        return Email::query()
            ->get()
            ->first(fn (Email $account) => in_array($staffId, $account->sharedStaffIds(), true));
    }
}
