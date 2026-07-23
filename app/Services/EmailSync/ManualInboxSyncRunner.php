<?php

namespace App\Services\EmailSync;

use App\Logging\InboxSyncLogger;
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
        $email = strtolower(trim($email));

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
            if ($email === '') {
                return [
                    'success' => false,
                    'message' => 'Please select a mailbox to sync.',
                    'mailboxes' => [],
                    'total_imported' => 0,
                    'total_skipped' => 0,
                    'total_failed' => 0,
                ];
            }

            $mailbox = IncomingEmailSyncService::findSyncableMailbox($email);
            if ($mailbox === null) {
                return [
                    'success' => false,
                    'message' => 'The selected mailbox is not available for sync.',
                    'mailboxes' => [],
                    'total_imported' => 0,
                    'total_skipped' => 0,
                    'total_failed' => 0,
                ];
            }

            return [
                'success' => true,
                'sync_range' => $syncRange,
                'email' => strtolower((string) $mailbox->email),
                'addresses' => [strtolower((string) $mailbox->email)],
            ];
        }

        $allowed = IncomingEmailSyncService::allowedSyncMailboxAddressesForStaff($staff);
        if ($allowed === []) {
            return [
                'success' => false,
                'message' => 'No synced mailbox is linked to your staff account. Configure it in Admin Console → Staff → Email & mailbox.',
                'mailboxes' => [],
                'total_imported' => 0,
                'total_skipped' => 0,
                'total_failed' => 0,
            ];
        }

        $target = $email !== '' ? $email : $allowed[0];
        if (! in_array($target, $allowed, true)) {
            return [
                'success' => false,
                'message' => 'You do not have permission to sync that mailbox.',
                'mailboxes' => [],
                'total_imported' => 0,
                'total_skipped' => 0,
                'total_failed' => 0,
            ];
        }

        return [
            'success' => true,
            'sync_range' => $syncRange,
            'email' => $target,
            'addresses' => [$target],
        ];
    }

    /**
     * @param  array<string, mixed>  $prepared
     * @return array<string, mixed>
     */
    public function execute(array $prepared): array
    {
        $syncRange = (string) ($prepared['sync_range'] ?? 'today');
        $email = strtolower(trim((string) ($prepared['email'] ?? '')));
        $addresses = is_array($prepared['addresses'] ?? null) ? $prepared['addresses'] : [];

        InboxSyncLogger::info('Inbox sync execution started', [
            'sync_range' => $syncRange,
            'email' => $email,
            'addresses' => $addresses,
        ]);

        $parserStatus = IncomingEmailSyncService::pythonParserStatus();
        if (! $parserStatus['available']) {
            IncomingEmailSyncService::markParserUnavailable();
            $message = (string) ($parserStatus['message'] ?? 'Email parser service is unavailable.');

            InboxSyncLogger::error('Inbox sync blocked — Python parser unavailable', [
                'sync_range' => $syncRange,
                'email' => $email,
                'parser_url' => $parserStatus['url'] ?? '',
            ]);

            return [
                'success' => false,
                'message' => $message,
                'sync_range' => $syncRange,
                'mailboxes' => [],
                'total_imported' => 0,
                'total_skipped' => 0,
                'total_failed' => 0,
            ];
        }

        $since = $syncRange === 'full'
            ? null
            : IncomingEmailSyncService::resolveSyncSince($syncRange);

        if ($syncRange === 'full' && $email !== '') {
            $this->syncService->resetUidTracking($email, null);
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

        $this->logExecutionResult($summary);

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function logExecutionResult(array $summary): void
    {
        $context = [
            'sync_range' => $summary['sync_range'] ?? null,
            'total_imported' => (int) ($summary['total_imported'] ?? 0),
            'total_skipped' => (int) ($summary['total_skipped'] ?? 0),
            'total_failed' => (int) ($summary['total_failed'] ?? 0),
            'mailboxes' => array_keys($summary['mailboxes'] ?? []),
        ];

        if (($summary['success'] ?? true) === false) {
            InboxSyncLogger::error('Inbox sync execution failed', array_merge($context, [
                'message' => $summary['message'] ?? 'Inbox sync failed.',
            ]));

            return;
        }

        if ((int) ($summary['total_failed'] ?? 0) > 0) {
            InboxSyncLogger::warning('Inbox sync execution completed with failures', $context);

            return;
        }

        InboxSyncLogger::info('Inbox sync execution completed', $context);
    }
}
