<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Support\StaffClientVisibility;

class EmailMatchingService
{
    private const HIGH_CONFIDENCE = 80;

    /**
     * Build ranked client/matter suggestions from parsed email data.
     *
     * @return array{
     *     suggestions: list<array<string, mixed>>,
     *     best: array<string, mixed>|null,
     *     confidence: int,
     *     is_high_confidence: bool,
     *     mail_type: string,
     *     matched_by: list<string>,
     *     is_ambiguous: bool,
     *     confidence_gap: int|null
     * }
     */
    public function suggestMatches(array $parsedData): array
    {
        $subject = (string) ($parsedData['subject'] ?? '');
        $textPreview = (string) ($parsedData['text_preview'] ?? $parsedData['text_content'] ?? '');
        $searchText = $subject . "\n" . mb_substr(strip_tags((string) ($parsedData['html_content'] ?? '')), 0, 2000) . "\n" . $textPreview;

        $senderAddresses = $this->extractEmailAddresses([
            'sender_email' => $parsedData['sender_email'] ?? null,
            'from_mail' => $parsedData['from_mail'] ?? null,
        ]);
        $recipientAddresses = $this->extractEmailAddresses([
            'to_recipients' => $parsedData['to_recipients'] ?? [],
            'cc_recipients' => $parsedData['cc_recipients'] ?? [],
            'recipients' => $parsedData['recipients'] ?? [],
            'bcc_recipients' => $parsedData['bcc_recipients'] ?? [],
        ]);
        $mailType = $this->detectMailType($parsedData);

        $candidates = [];

        foreach ($this->matchByMatterReference($searchText) as $match) {
            $this->addCandidate($candidates, $match);
        }

        foreach ($this->matchByClientReference($searchText) as $match) {
            $this->addCandidate($candidates, $match);
        }

        // For incoming mail, the sender is stronger evidence than To/Cc/Bcc.
        // Treating every participant equally caused unrelated copied recipients to win ties.
        foreach ($this->matchByEmailAddresses($senderAddresses, 85, 'sender_email') as $match) {
            $this->addCandidate($candidates, $match);
        }

        foreach ($this->matchByEmailAddresses($recipientAddresses, 70, 'recipient_email') as $match) {
            $this->addCandidate($candidates, $match);
        }

        $suggestions = array_values($candidates);
        usort($suggestions, static fn (array $a, array $b) => $b['confidence'] <=> $a['confidence']);

        $best = $suggestions[0] ?? null;
        $confidence = (int) ($best['confidence'] ?? 0);
        $matchedBy = $best ? array_values(array_unique($best['matched_by'] ?? [])) : [];
        $runnerUp = null;
        foreach (array_slice($suggestions, 1) as $suggestion) {
            if ((int) ($suggestion['client_id'] ?? 0) !== (int) ($best['client_id'] ?? 0)) {
                $runnerUp = $suggestion;
                break;
            }
        }
        $confidenceGap = $runnerUp
            ? $confidence - (int) ($runnerUp['confidence'] ?? 0)
            : null;
        $isAmbiguous = $runnerUp !== null && $confidenceGap < 10;

        return [
            'suggestions' => array_slice($suggestions, 0, 5),
            'best' => $best,
            'confidence' => $confidence,
            'is_high_confidence' => $confidence >= self::HIGH_CONFIDENCE && ! $isAmbiguous,
            'mail_type' => $mailType,
            'matched_by' => $matchedBy,
            'is_ambiguous' => $isAmbiguous,
            'confidence_gap' => $confidenceGap,
        ];
    }

    /**
     * @return list<string>
     */
    public function extractEmailAddresses(array $parsedData): array
    {
        $raw = [
            $parsedData['sender_email'] ?? null,
            $parsedData['from_mail'] ?? null,
        ];

        foreach (['to_recipients', 'cc_recipients', 'recipients', 'bcc_recipients'] as $key) {
            $list = $parsedData[$key] ?? [];
            if (is_array($list)) {
                foreach ($list as $entry) {
                    $raw[] = $entry;
                }
            }
        }

        $emails = [];
        foreach ($raw as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $value = strtolower(trim((string) $value));
            if (preg_match('/<([^>]+)>/', $value, $m)) {
                $value = strtolower(trim($m[1]));
            }
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $value;
            } elseif (preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $value, $matches)) {
                foreach ($matches[0] as $found) {
                    $emails[] = strtolower($found);
                }
            }
        }

        return array_values(array_unique($emails));
    }

    public function detectMailType(array $parsedData): string
    {
        $sender = strtolower((string) ($parsedData['sender_email'] ?? ''));
        foreach ($this->companyDomains() as $domain) {
            if (str_contains($sender, $domain)) {
                return 'sent';
            }
        }

        return 'inbox';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function matchByMatterReference(string $searchText): array
    {
        $refs = $this->extractMatterReferences($searchText);
        if ($refs === []) {
            return [];
        }

        $matches = [];

        // Start from Admin::query() so restrictAdminEloquentQuery works correctly
        // (it uses getModel()->getTable() internally which must return 'admins').
        $query = Admin::query()
            ->join('client_matters', 'client_matters.client_id', '=', 'admins.id')
            ->leftJoin('matters', 'matters.id', '=', 'client_matters.sel_matter_id')
            ->whereIn('admins.type', ['client', 'lead'])
            ->whereNull('admins.is_deleted')
            ->where('admins.is_archived', 0);

        StaffClientVisibility::restrictAdminEloquentQuery($query);

        $query->where(function ($q) use ($refs) {
            foreach ($refs as $ref) {
                $q->orWhereRaw('LOWER(client_matters.client_unique_matter_no) = ?', [strtolower($ref)]);
            }
        });

        $rows = $query->select(
            'client_matters.id as client_matter_id',
            'client_matters.client_unique_matter_no',
            'client_matters.matter_status',
            'admins.id as client_id',
            'admins.client_id as client_ref',
            'admins.first_name',
            'admins.last_name',
            'admins.email',
            'admins.type as record_type',
            'admins.is_company',
            'matters.title as matter_title'
        )->get();

        foreach ($rows as $row) {
            $matches[] = $this->formatCandidate(
                (int) $row->client_id,
                (int) $row->client_matter_id,
                (string) $row->client_ref,
                trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                (string) ($row->email ?? ''),
                (string) $row->client_unique_matter_no,
                (string) ($row->matter_title ?? ''),
                (string) ($row->record_type ?? 'client'),
                92,
                'matter_reference',
                (int) $row->matter_status === 1
            );
        }

        return $matches;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function matchByClientReference(string $searchText): array
    {
        $refs = $this->extractClientReferences($searchText);
        if ($refs === []) {
            return [];
        }

        $matches = [];
        $query = Admin::query()
            ->whereIn('type', ['client', 'lead'])
            ->whereNull('is_deleted')
            ->where('is_archived', 0);

        StaffClientVisibility::restrictAdminEloquentQuery($query);

        $query->where(function ($q) use ($refs) {
            foreach ($refs as $ref) {
                $q->orWhereRaw('LOWER(client_id) = ?', [strtolower($ref)]);
            }
        });

        $clients = $query->select('id', 'client_id', 'first_name', 'last_name', 'email', 'type')->get();

        foreach ($clients as $client) {
            $matter = $this->resolveMatterForClient((int) $client->id);
            if (! $matter) {
                continue;
            }

            $matches[] = $this->formatCandidate(
                (int) $client->id,
                (int) $matter->id,
                (string) $client->client_id,
                trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
                (string) ($client->email ?? ''),
                (string) $matter->client_unique_matter_no,
                (string) ($matter->matter_title ?? ''),
                (string) $client->type,
                88,
                'client_reference',
                (int) $matter->matter_status === 1
            );
        }

        return $matches;
    }

    /**
     * @param list<string> $addresses
     * @return list<array<string, mixed>>
     */
    private function matchByEmailAddresses(
        array $addresses,
        int $confidence,
        string $matchedBy
    ): array
    {
        if ($addresses === []) {
            return [];
        }

        $externalAddresses = array_values(array_filter(
            $addresses,
            fn (string $email) => ! $this->isCompanyEmail($email)
        ));

        if ($externalAddresses === []) {
            return [];
        }

        $matches = [];

        $adminMatches = Admin::query()
            ->whereIn('type', ['client', 'lead'])
            ->whereNull('is_deleted')
            ->where('is_archived', 0)
            ->where(function ($q) use ($externalAddresses) {
                foreach ($externalAddresses as $email) {
                    $q->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            });

        StaffClientVisibility::restrictAdminEloquentQuery($adminMatches);

        foreach ($adminMatches->select('id', 'client_id', 'first_name', 'last_name', 'email', 'type')->get() as $client) {
            $matter = $this->resolveMatterForClient((int) $client->id);

            $matches[] = $this->formatCandidate(
                (int) $client->id,
                $matter ? (int) $matter->id : 0,
                (string) $client->client_id,
                trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
                (string) ($client->email ?? ''),
                $matter ? (string) $matter->client_unique_matter_no : '',
                $matter ? (string) ($matter->matter_title ?? '') : '',
                (string) $client->type,
                $confidence,
                $matchedBy,
                $matter ? (int) $matter->matter_status === 1 : false
            );
        }

        // Start from Admin::query() so restrictAdminEloquentQuery works correctly.
        $clientEmailMatches = Admin::query()
            ->join('client_emails', 'client_emails.client_id', '=', 'admins.id')
            ->whereIn('admins.type', ['client', 'lead'])
            ->whereNull('admins.is_deleted')
            ->where('admins.is_archived', 0)
            ->where(function ($q) use ($externalAddresses) {
                foreach ($externalAddresses as $email) {
                    $q->orWhereRaw('LOWER(client_emails.email) = ?', [$email]);
                }
            });

        StaffClientVisibility::restrictAdminEloquentQuery($clientEmailMatches);

        $rows = $clientEmailMatches->select(
            'admins.id as client_id',
            'admins.client_id as client_ref',
            'admins.first_name',
            'admins.last_name',
            'admins.email as primary_email',
            'admins.type as record_type',
            'client_emails.email as matched_email'
        )->get();

        foreach ($rows as $row) {
            $matter = $this->resolveMatterForClient((int) $row->client_id);

            $matches[] = $this->formatCandidate(
                (int) $row->client_id,
                $matter ? (int) $matter->id : 0,
                (string) $row->client_ref,
                trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                (string) ($row->matched_email ?: $row->primary_email),
                $matter ? (string) $matter->client_unique_matter_no : '',
                $matter ? (string) ($matter->matter_title ?? '') : '',
                (string) $row->record_type,
                $confidence,
                $matchedBy,
                $matter ? (int) $matter->matter_status === 1 : false
            );
        }

        return $matches;
    }

    private function resolveMatterForClient(int $clientId): ?object
    {
        $active = ClientMatter::query()
            ->leftJoin('matters', 'matters.id', '=', 'client_matters.sel_matter_id')
            ->where('client_matters.client_id', $clientId)
            ->where('client_matters.matter_status', 1)
            ->orderByDesc('client_matters.id')
            ->select(
                'client_matters.id',
                'client_matters.client_unique_matter_no',
                'client_matters.matter_status',
                'matters.title as matter_title'
            )
            ->first();

        if ($active) {
            return $active;
        }

        return ClientMatter::query()
            ->leftJoin('matters', 'matters.id', '=', 'client_matters.sel_matter_id')
            ->where('client_matters.client_id', $clientId)
            ->orderByDesc('client_matters.id')
            ->select(
                'client_matters.id',
                'client_matters.client_unique_matter_no',
                'client_matters.matter_status',
                'matters.title as matter_title'
            )
            ->first();
    }

    /**
     * @return list<string>
     */
    private function extractMatterReferences(string $text): array
    {
        $refs = [];
        if (preg_match_all('/\b([A-Z]{2,6}_[0-9]{4}_[0-9]{1,6})\b/i', $text, $matches)) {
            foreach ($matches[1] as $ref) {
                $refs[] = strtoupper($ref);
            }
        }
        if (preg_match_all('/\b([A-Z]{2,6}_[0-9]{1,6})\b/i', $text, $matches)) {
            foreach ($matches[1] as $ref) {
                $refs[] = strtoupper($ref);
            }
        }

        return array_values(array_unique($refs));
    }

    /**
     * @return list<string>
     */
    private function extractClientReferences(string $text): array
    {
        $refs = [];
        if (preg_match_all('/\b(CLI[0-9]{4,}[A-Z0-9]*)\b/i', $text, $matches)) {
            foreach ($matches[1] as $ref) {
                $refs[] = strtoupper($ref);
            }
        }

        return array_values(array_unique($refs));
    }

    private function isCompanyEmail(string $email): bool
    {
        foreach ($this->companyDomains() as $domain) {
            if (str_contains($email, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function companyDomains(): array
    {
        $domains = config('app.brand.firm_email_domains', []);

        return is_array($domains) ? array_values(array_filter($domains)) : [];
    }

    /**
     * @param array<string, array<string, mixed>> $candidates
     * @param array<string, mixed> $match
     */
    private function addCandidate(array &$candidates, array $match): void
    {
        $key = $match['client_id'] . ':' . $match['client_matter_id'];
        if (! isset($candidates[$key])) {
            $candidates[$key] = $match;

            return;
        }

        $existing = $candidates[$key];
        $existing['confidence'] = min(99, (int) $existing['confidence'] + 5);
        $existing['matched_by'] = array_values(array_unique(array_merge(
            $existing['matched_by'] ?? [],
            $match['matched_by'] ?? []
        )));
        $candidates[$key] = $existing;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCandidate(
        int $clientId,
        int $clientMatterId,
        string $clientRef,
        string $clientName,
        string $email,
        string $matterNo,
        string $matterTitle,
        string $recordType,
        int $confidence,
        string $matchedBy,
        bool $matterActive
    ): array {
        if (! $matterActive) {
            $confidence = max(50, $confidence - 10);
        }

        return [
            'client_id' => $clientId,
            'client_matter_id' => $clientMatterId,
            'client_ref' => $clientRef,
            'client_name' => trim($clientName) !== '' ? trim($clientName) : $clientRef,
            'email' => $email,
            'matter_no' => $matterNo,
            'matter_title' => $matterTitle,
            'record_type' => $recordType,
            'confidence' => $confidence,
            'matched_by' => [$matchedBy],
            'matter_active' => $matterActive,
        ];
    }
}
