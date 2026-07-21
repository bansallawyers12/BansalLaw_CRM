<?php

namespace App\Services\EmailSync;

use App\Models\Staff;

class ManualInboxSyncRunner
{
    public function __construct(
        private IncomingEmailSyncService $syncService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function prepare(Staff $staff, string $syncRange, string $email = ''): array
    {
        $syncRange = strtolower(trim($syncRange));
        $email = trim($email);

        if (! IncomingEmailSyncService::isValidSyncRange($syncRange)) {
            return [
                'success' => false,
                'message' => 'Invalid sync range selected.',
                'mailboxes' => [],
                'total_imported' => 0,
                'total_skipped' => 0,
                'total_failed' => 0,
            ];
        }

        if ($staff->canViewAllSyncedInboxMail()) {
            return [
                'success' => true,
                'sync_range' => $syncRange,
                'view_all' => true,
                'email' => $email,
                'addresses' => [],
            ];
        }

        $addresses = [];
        if ($email !== '') {
            $addresses = [strtolower($email)];
        } else {
            $addresses = IncomingEmailSyncService::mailboxAddressesForStaff((int) $staff->id, $staff->email);
            if ($addresses === [] && trim((string) $staff->email) !== '') {
                $addresses = [strtolower(trim((string) $staff->email))];
            }
        }

        if ($addresses === []) {
            return [
                'success' => false,
                'message' => 'No synced mailbox is linked to your staff account. Configure it in Admin Console → Staff → Email & mailbox.',
                'mailboxes' => [],
                'total_imported' => 0,
                'total_skipped' => 0,
                'total_failed' => 0,
            ];
        }

        return [
            'success' => true,
            'sync_range' => $syncRange,
            'view_all' => false,
            'email' => $email,
            'addresses' => $addresses,
        ];
    }

    /**
     * @param  array<string, mixed>  $prepared
     * @return array<string, mixed>
     */
    public function execute(array $prepared): array
    {
        $syncRange = (string) ($prepared['sync_range'] ?? 'today');
        $email = trim((string) ($prepared['email'] ?? ''));
        $since = $syncRange === 'full'
            ? null
            : IncomingEmailSyncService::resolveSyncSince($syncRange);

        if (! empty($prepared['view_all'])) {
            if ($syncRange === 'full') {
                $this->syncService->resetUidTracking(null, null);
            }

            $summary = $this->syncService->syncAll(null, $since);
            $summary['sync_range'] = $syncRange;

            return $summary;
        }

        $addresses = is_array($prepared['addresses'] ?? null) ? $prepared['addresses'] : [];

        if ($syncRange === 'full') {
            $this->syncService->resetUidTracking(
                $email !== '' ? $email : null,
                $email === '' ? $addresses : null
            );
        }

        $summary = [
            'success' => true,
            'sync_range' => $syncRange,
            'mailboxes' => [],
            'total_imported' => 0,
            'total_skipped' => 0,
            'total_failed' => 0,
        ];

        foreach ($addresses as $address) {
            $partial = $this->syncService->syncAll($address, $since);
            if (($partial['success'] ?? true) === false) {
                $summary['success'] = false;
                $summary['message'] = $partial['message'] ?? 'Inbox sync is disabled.';
            }
            foreach ($partial['mailboxes'] ?? [] as $mailboxEmail => $result) {
                $summary['mailboxes'][$mailboxEmail] = $result;
                $summary['total_imported'] += (int) ($result['imported'] ?? 0);
                $summary['total_skipped'] += (int) ($result['skipped'] ?? 0);
                $summary['total_failed'] += (int) ($result['failed'] ?? 0);
            }
        }

        return $summary;
    }
}
