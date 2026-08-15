<?php

namespace App\Services\EmailSync;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\EmailLog;
use App\Models\Staff;

class AutoAssignmentReviewService
{
    private const INTERNAL_DOMAINS = [
        '@lead.internal',
    ];

    /**
     * Find client-assigned emails that need a human review.
     *
     * Read-only. Uses existing email/client data:
     * - a participant address belongs to a different client than the assigned one;
     * - an address is linked to multiple client/lead records; or
     * - nothing in the email supports the assignment (auto-assigned mail only).
     *
     * @return array<int, array{type: string, reason: string, matched_client_count: int, is_synced: bool}>
     */
    public function reviewItemsForStaff(Staff $staff): array
    {
        $query = EmailLog::query()
            ->whereNotNull('client_id')
            ->select([
                'id',
                'client_id',
                'client_matter_id',
                'from_mail',
                'to_mail',
                'cc',
                'bcc',
                'subject',
                'message',
                'sync_assignment_status',
                'synced_email_id',
            ]);

        IncomingEmailSyncService::applySyncedInboxVisibilityFilter($query, $staff);

        $emailClientMap = $this->clientIdsByEmail();
        $clientRefs = $this->clientReferences();
        $matterRefs = $this->matterReferences();
        $items = [];

        $query->orderBy('id')->chunkById(300, function ($emailLogs) use (
            &$items,
            $emailClientMap,
            $clientRefs,
            $matterRefs
        ) {
            foreach ($emailLogs as $emailLog) {
                $item = $this->reviewItem($emailLog, $emailClientMap, $clientRefs, $matterRefs);
                if ($item !== null) {
                    $items[(int) $emailLog->id] = $item;
                }
            }
        });

        return $items;
    }

    public function countForStaff(Staff $staff): int
    {
        return count($this->reviewItemsForStaff($staff));
    }

    /**
     * @param array<string, list<int>> $emailClientMap
     * @param array<int, string> $clientRefs
     * @param array<int, string> $matterRefs
     * @return array{type: string, reason: string, matched_client_count: int, is_synced: bool}|null
     */
    private function reviewItem(
        EmailLog $emailLog,
        array $emailClientMap,
        array $clientRefs,
        array $matterRefs
    ): ?array {
        $assignedClientId = (int) $emailLog->client_id;
        $isSynced = ! empty($emailLog->synced_email_id);
        $isAutoAssigned = $isSynced && $emailLog->sync_assignment_status === 'auto_assigned';

        // A human already picked this client from the reassign modal, so the
        // heuristics below must not pull the message straight back into review.
        if ($emailLog->sync_assignment_status === 'manual_assigned') {
            return null;
        }

        $matchedClientIds = [];
        foreach ($this->externalParticipantEmails($emailLog) as $address) {
            foreach ($emailClientMap[$address] ?? [] as $clientId) {
                $matchedClientIds[$clientId] = true;
            }
        }

        $matchedIds = array_map('intval', array_keys($matchedClientIds));
        $matchedCount = count($matchedIds);
        $hasStoredReference = $this->containsAssignedReference($emailLog, $clientRefs, $matterRefs);

        if ($matchedCount > 0 && ! in_array($assignedClientId, $matchedIds, true) && ! $hasStoredReference) {
            return [
                'type' => 'different_client',
                'reason' => 'Sender or recipient email belongs to a different client.',
                'matched_client_count' => $matchedCount,
                'is_synced' => $isSynced,
            ];
        }

        // Weaker signals would be noisy for manually filed mail, so they only
        // apply to messages the sync auto-assigned without human review.
        if (! $isAutoAssigned || $hasStoredReference) {
            return null;
        }

        if ($matchedCount > 1) {
            return [
                'type' => 'ambiguous_email',
                'reason' => 'An email address is linked to multiple client or lead records.',
                'matched_client_count' => $matchedCount,
                'is_synced' => $isSynced,
            ];
        }

        if ($matchedCount === 0) {
            return [
                'type' => 'no_supporting_match',
                'reason' => 'No client email or stored client/matter reference supports this assignment.',
                'matched_client_count' => 0,
                'is_synced' => $isSynced,
            ];
        }

        return null;
    }

    /**
     * @return array<string, list<int>>
     */
    private function clientIdsByEmail(): array
    {
        $map = [];

        $clients = Admin::query()
            ->whereIn('type', ['client', 'lead'])
            ->whereNull('is_deleted')
            ->where('is_archived', 0)
            ->whereNotNull('email')
            ->get(['id', 'email']);

        foreach ($clients as $client) {
            $this->addEmailClient($map, (string) $client->email, (int) $client->id);
        }

        $additionalEmails = Admin::query()
            ->join('client_emails', 'client_emails.client_id', '=', 'admins.id')
            ->whereIn('admins.type', ['client', 'lead'])
            ->whereNull('admins.is_deleted')
            ->where('admins.is_archived', 0)
            ->whereNotNull('client_emails.email')
            ->get([
                'admins.id as client_id',
                'client_emails.email',
            ]);

        foreach ($additionalEmails as $row) {
            $this->addEmailClient($map, (string) $row->email, (int) $row->client_id);
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function clientReferences(): array
    {
        return Admin::query()
            ->whereIn('type', ['client', 'lead'])
            ->whereNotNull('client_id')
            ->pluck('client_id', 'id')
            ->map(fn ($ref) => strtolower(trim((string) $ref)))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function matterReferences(): array
    {
        return ClientMatter::query()
            ->whereNotNull('client_unique_matter_no')
            ->pluck('client_unique_matter_no', 'id')
            ->map(fn ($ref) => strtolower(trim((string) $ref)))
            ->all();
    }

    /**
     * @param array<string, list<int>> $map
     */
    private function addEmailClient(array &$map, string $email, int $clientId): void
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $this->isFirmAddress($email)) {
            return;
        }

        $map[$email] ??= [];
        if (! in_array($clientId, $map[$email], true)) {
            $map[$email][] = $clientId;
        }
    }

    /**
     * @return list<string>
     */
    private function externalParticipantEmails(EmailLog $emailLog): array
    {
        $raw = implode(' ', [
            (string) $emailLog->from_mail,
            (string) $emailLog->to_mail,
            (string) $emailLog->cc,
            (string) $emailLog->bcc,
        ]);

        preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $raw, $matches);

        return array_values(array_unique(array_filter(
            array_map(static fn (string $email): string => strtolower($email), $matches[0] ?? []),
            fn (string $email): bool => ! $this->isFirmAddress($email)
        )));
    }

    private function isFirmAddress(string $email): bool
    {
        $email = strtolower($email);
        foreach ($this->firmDomains() as $domain) {
            if (str_ends_with($email, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function firmDomains(): array
    {
        $configured = config('app.brand.firm_email_domains', []);
        $configured = is_array($configured) ? $configured : [];

        return array_values(array_unique(array_filter(array_merge(
            self::INTERNAL_DOMAINS,
            $configured
        ))));
    }

    /**
     * @param array<int, string> $clientRefs
     * @param array<int, string> $matterRefs
     */
    private function containsAssignedReference(EmailLog $emailLog, array $clientRefs, array $matterRefs): bool
    {
        $text = strtolower(strip_tags(
            (string) $emailLog->subject . "\n" . mb_substr((string) $emailLog->message, 0, 4000)
        ));

        $clientRef = $clientRefs[(int) $emailLog->client_id] ?? '';
        if ($clientRef !== '' && str_contains($text, $clientRef)) {
            return true;
        }

        $matterRef = $matterRefs[(int) $emailLog->client_matter_id] ?? '';

        return $matterRef !== '' && str_contains($text, $matterRef);
    }
}
