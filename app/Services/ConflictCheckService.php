<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientConflictParty;
use App\Models\ClientEmail;
use App\Models\ClientMatter;
use App\Models\ClientMatterOpposingParty;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConflictCheckService
{
    private const MAX_MATCHES = 50;

    /**
     * Run an automated conflict search for a client/lead.
     *
     * @return array{
     *     search_terms: array,
     *     matches: list<array>,
     *     informational_matches: list<array>,
     *     suggested_outcome: string,
     *     match_count: int,
     *     informational_count: int,
     *     warnings: list<string>,
     *     party_count: int
     * }
     */
    public function run(Admin $client, ?int $clientMatterId = null): array
    {
        $client->loadMissing('company');

        $parties = \App\Support\MatterOtherPartiesHelper::loadForConflictSearch((int) $client->id, $clientMatterId);

        $searchTerms = $this->buildSearchTerms($client, $parties);

        if (! $this->hasSearchableTerms($searchTerms)) {
            throw new \InvalidArgumentException(
                'Not enough information to run a conflict search. Ensure the client has a name, email, phone, or company details, or save at least one other party first.'
            );
        }

        $warnings = $this->buildWarnings($parties, $searchTerms);
        $known = $this->buildKnownPartyContext($searchTerms, (int) $client->id);
        $matches = [];

        $this->searchAdmins($client, $searchTerms, $known, $matches);
        $this->searchConflictPartiesOnOtherClients($client, $searchTerms, $known, $matches);
        $this->searchMatterOpposingParties($client, $searchTerms, $known, $matches);
        $this->searchCompanies($client, $searchTerms, $known, $matches);

        [$hardMatches, $infoMatches] = $this->splitAndFinalizeMatches($matches, $known, (int) $client->id);

        return [
            'search_terms' => $searchTerms,
            'matches' => $hardMatches,
            'informational_matches' => $infoMatches,
            'suggested_outcome' => count($hardMatches) === 0 ? 'clear' : 'pending',
            'match_count' => count($hardMatches),
            'informational_count' => count($infoMatches),
            'warnings' => $warnings,
            'party_count' => $parties->count(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ClientConflictParty>  $parties
     * @return list<string>
     */
    private function buildWarnings($parties, array $searchTerms): array
    {
        $warnings = [];

        if ($parties->isEmpty()) {
            $warnings[] = 'No opposing parties are saved for this matter. The search ran on this client\'s details only. Save other parties first for a fuller check.';
        }

        $partyTerms = $searchTerms['parties'] ?? [];
        $incompleteParties = 0;
        foreach ($partyTerms as $pt) {
            $hasLead = ! empty($pt['opposing_lead_id']);
            $hasName = trim((string) ($pt['name'] ?? '')) !== '' && strtolower(trim($pt['name'])) !== 'unnamed';
            if (! $hasLead && ! $hasName) {
                $incompleteParties++;
            }
        }
        if ($incompleteParties > 0) {
            $warnings[] = $incompleteParties . ' saved party row(s) have no other party selected — only complete rows were included.';
        }

        return $warnings;
    }

    private function hasSearchableTerms(array $searchTerms): bool
    {
        $subject = $searchTerms['subject'] ?? [];
        if ($this->isMeaningfulName($subject['name'] ?? null)
            || ! empty($subject['emails'])
            || ! empty($subject['phones'])
            || $this->isMeaningfulName($subject['company_name'] ?? null)
            || ! empty($subject['abn'])
            || ! empty($subject['acn'])) {
            return true;
        }

        foreach ($searchTerms['parties'] ?? [] as $party) {
            if ($this->isMeaningfulName($party['name'] ?? null)
                || ! empty($party['emails'])
                || ! empty($party['phones'])
                || $this->isMeaningfulName($party['company_name'] ?? null)
                || $this->isMeaningfulName($party['trading_name'] ?? null)
                || ! empty($party['abn'])
                || ! empty($party['acn'])) {
                return true;
            }
        }

        return false;
    }

    private function isMeaningfulName(?string $value): bool
    {
        $value = strtolower(trim((string) $value));
        if ($value === '' || strlen($value) < 2) {
            return false;
        }

        $placeholders = [
            'unnamed',
            'unnamed company',
            'record #',
        ];

        foreach ($placeholders as $placeholder) {
            if ($value === $placeholder || str_starts_with($value, 'record #')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Quote a column identifier for raw SQL (PostgreSQL mixed-case columns need quotes).
     */
    private function sqlColumn(string $column): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return '"' . str_replace('"', '""', $column) . '"';
        }

        return $column;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ClientConflictParty>  $parties
     * @return array{subject: array, parties: list<array>, terms: list<string>}
     */
    private function buildSearchTerms(Admin $client, $parties): array
    {
        $subject = [
            'id' => $client->id,
            'name' => $this->displayName($client),
            'emails' => $this->collectEmailsForAdmin($client),
            'phones' => $this->collectPhonesForAdmin($client),
            'company_name' => $this->companyNameForAdmin($client),
            'abn' => null,
            'acn' => null,
            'dob' => $client->dob ? $client->dob->format('Y-m-d') : null,
        ];

        if ($client->company) {
            $subject['abn'] = $this->digitsOnly($client->company->ABN_number ?? null);
            $subject['acn'] = $this->digitsOnly($client->company->ACN ?? null);
            if (empty($subject['company_name'])) {
                $subject['company_name'] = trim((string) ($client->company->company_name ?? ''));
            }
        }

        $partyTerms = [];
        foreach ($parties as $party) {
            $name = $this->partyDisplayName($party);
            $emails = $party->emails->pluck('email')->filter()->map(fn ($e) => strtolower(trim($e)))->values()->all();
            $phones = $party->phones->pluck('phone')->filter()->map(fn ($p) => $this->normalizePhone($p))->filter()->values()->all();

            if ($party->opposingLead) {
                $lead = $party->opposingLead;
                $emails = array_values(array_unique(array_merge($emails, $this->collectEmailsForAdmin($lead))));
                $phones = array_values(array_unique(array_merge($phones, $this->collectPhonesForAdmin($lead))));
            }

            $repEmail = strtolower(trim((string) ($party->rep_email ?? '')));
            if ($repEmail !== '' && ! str_ends_with($repEmail, '@lead.internal')) {
                $emails[] = $repEmail;
            }
            $repPhone = $this->normalizePhone($party->rep_phone ?? null);
            if ($repPhone) {
                $phones[] = $repPhone;
            }
            $emails = array_values(array_unique($emails));
            $phones = array_values(array_unique($phones));

            $partyTerms[] = [
                'id' => $party->id,
                'name' => $name,
                'party_role' => $party->party_role,
                'emails' => $emails,
                'phones' => $phones,
                'company_name' => trim((string) ($party->company_name ?? '')),
                'trading_name' => trim((string) ($party->trading_name ?? '')),
                'abn' => $this->digitsOnly($party->abn),
                'acn' => $this->digitsOnly($party->acn),
                'dob' => $party->dob ? $party->dob->format('Y-m-d') : null,
                'opposing_lead_id' => $party->opposing_lead_id,
            ];
        }

        $flat = [];
        $this->pushTerm($flat, $subject['name'] ?? null);
        foreach ($subject['emails'] as $e) {
            $this->pushTerm($flat, $e);
        }
        foreach ($subject['phones'] as $p) {
            $this->pushTerm($flat, $p);
        }
        $this->pushTerm($flat, $subject['company_name'] ?? null);
        $this->pushTerm($flat, $subject['abn'] ?? null);
        $this->pushTerm($flat, $subject['acn'] ?? null);

        foreach ($partyTerms as $pt) {
            $this->pushTerm($flat, $pt['name'] ?? null);
            $this->pushTerm($flat, $pt['company_name'] ?? null);
            $this->pushTerm($flat, $pt['trading_name'] ?? null);
            $this->pushTerm($flat, $pt['abn'] ?? null);
            $this->pushTerm($flat, $pt['acn'] ?? null);
            foreach ($pt['emails'] as $e) {
                $this->pushTerm($flat, $e);
            }
            foreach ($pt['phones'] as $p) {
                $this->pushTerm($flat, $p);
            }
        }

        return [
            'subject' => $subject,
            'parties' => $partyTerms,
            'terms' => array_values(array_unique($flat)),
        ];
    }

    /**
     * @param  array{subject: array, parties: list<array>, terms: list<string>}  $searchTerms
     * @return array{
     *   admin_ids: list<int>,
     *   identity: list<array{name: string, emails: list<string>, phones: list<string>, abns: list<string>, acns: list<string>}>
     * }
     */
    private function buildKnownPartyContext(array $searchTerms, int $subjectId): array
    {
        $adminIds = [$subjectId];
        $identity = [];

        foreach ($searchTerms['parties'] ?? [] as $party) {
            $leadId = (int) ($party['opposing_lead_id'] ?? 0);
            if ($leadId > 0) {
                $adminIds[] = $leadId;

                continue;
            }

            $abns = [];
            $acns = [];
            if (! empty($party['abn'])) {
                $abns[] = $party['abn'];
            }
            if (! empty($party['acn'])) {
                $acns[] = $party['acn'];
            }

            $identity[] = [
                'name' => strtolower(trim((string) ($party['name'] ?? ''))),
                'emails' => array_values(array_unique($party['emails'] ?? [])),
                'phones' => array_values(array_unique($party['phones'] ?? [])),
                'abns' => $abns,
                'acns' => $acns,
            ];
        }

        return [
            'admin_ids' => array_values(array_unique($adminIds)),
            'identity' => $identity,
        ];
    }

    /**
     * @param  array{admin_ids: list<int>, identity: list<array>}  $known
     */
    private function isExcludedAdminId(int $adminId, array $known): bool
    {
        return in_array($adminId, $known['admin_ids'], true);
    }

    /**
     * @param  array{admin_ids: list<int>, identity: list<array>}  $known
     */
    private function isKnownPartyIdentityMatch(array $match, array $known): bool
    {
        foreach ($known['identity'] as $identity) {
            $name = trim((string) ($identity['name'] ?? ''));
            $matchName = strtolower(trim((string) ($match['name'] ?? '')));
            if ($name !== '' && $matchName !== '' && $this->namesLooselyMatch($matchName, $name)) {
                return true;
            }

            $matchedOn = strtolower((string) ($match['matched_on'] ?? ''));
            foreach ($identity['emails'] as $email) {
                if ($email !== '' && str_contains($matchedOn, 'email:' . strtolower($email))) {
                    return true;
                }
            }
            foreach ($identity['phones'] as $phone) {
                if ($phone !== '' && str_contains($matchedOn, 'phone:' . $phone)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array{admin_ids: list<int>, identity: list<array>}  $known
     */
    private function isKnownPartyIdentityMatchForAdmin(Admin $admin, array $known): bool
    {
        if (empty($admin->is_other_party)) {
            return false;
        }

        $candidate = [
            'name' => $this->displayName($admin),
            'matched_on' => '',
        ];

        if ($this->isKnownPartyIdentityMatch($candidate, $known)) {
            return true;
        }

        $adminEmails = $this->collectEmailsForAdmin($admin);
        $adminPhones = $this->collectPhonesForAdmin($admin);

        foreach ($known['identity'] as $identity) {
            foreach ($identity['emails'] as $email) {
                if ($email !== '' && in_array($email, $adminEmails, true)) {
                    return true;
                }
            }
            foreach ($identity['phones'] as $phone) {
                if ($phone !== '' && in_array($phone, $adminPhones, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array{admin_ids: list<int>, identity: list<array>}  $known
     */
    private function isSharedOtherPartyOnOtherClient(array $match, array $known): bool
    {
        $source = (string) ($match['source'] ?? '');
        if (! in_array($source, ['matter_opposing_party', 'conflict_party'], true)) {
            return false;
        }

        $oppLeadId = (int) ($match['opposing_lead_id'] ?? 0);

        return $oppLeadId > 0 && in_array($oppLeadId, $known['admin_ids'], true);
    }

    /**
     * @param  list<array>  $matches
     * @param  array{admin_ids: list<int>, identity: list<array>}  $known
     * @return array{0: list<array>, 1: list<array>}
     */
    private function splitAndFinalizeMatches(array $matches, array $known, int $subjectId): array
    {
        $matches = $this->dedupeCrossClientPartyDuplicates($matches);

        $hard = [];
        $informational = [];

        foreach ($matches as $match) {
            $classified = $this->classifyMatch($match, $known, $subjectId);
            if ($classified === null) {
                continue;
            }

            if (($classified['severity'] ?? '') === 'informational') {
                $informational[] = $classified;
            } else {
                $hard[] = $classified;
            }
        }

        return [
            $this->dedupeAndRank($hard),
            $this->dedupeAndRank($informational),
        ];
    }

    /**
     * @param  array{admin_ids: list<int>, identity: list<array>}  $known
     * @return array|null
     */
    private function classifyMatch(array $match, array $known, int $subjectId): ?array
    {
        $clientId = (int) ($match['client_id'] ?? 0);
        if ($clientId > 0 && $this->isExcludedAdminId($clientId, $known)) {
            return null;
        }

        $match['is_cross_client'] = $clientId > 0 && $clientId !== $subjectId;

        if ($this->isSharedOtherPartyOnOtherClient($match, $known)) {
            $match['severity'] = 'informational';
            $match['is_known_party'] = true;
            $match['informational_reason'] = 'Same other-party record on another client\'s matter';

            return $match;
        }

        $match['severity'] = 'hard';
        $match['is_known_party'] = false;
        $match['informational_reason'] = null;

        return $match;
    }

    /**
     * @param  list<array>  $matches
     * @return list<array>
     */
    private function dedupeCrossClientPartyDuplicates(array $matches): array
    {
        $standalone = [];
        $groups = [];

        foreach ($matches as $match) {
            $source = (string) ($match['source'] ?? '');
            if (! in_array($source, ['conflict_party', 'matter_opposing_party'], true)) {
                $standalone[] = $match;

                continue;
            }

            $ownerId = (int) ($match['client_id'] ?? 0);
            $oppLeadId = (int) ($match['opposing_lead_id'] ?? 0);
            $key = $oppLeadId > 0
                ? ($ownerId . ':lead:' . $oppLeadId)
                : ($ownerId . ':name:' . strtolower(trim((string) ($match['name'] ?? ''))));

            if (! isset($groups[$key])) {
                $groups[$key] = $match;

                continue;
            }

            if (($groups[$key]['source'] ?? '') === 'conflict_party' && $source === 'matter_opposing_party') {
                $groups[$key] = $match;
            }
        }

        return array_merge($standalone, array_values($groups));
    }

    /**
     * @param  array{subject: array, parties: list<array>, terms: list<string>}  $searchTerms
     * @param  array{admin_ids: list<int>, identity: list<array>}  $known
     * @param  list<array>  $matches
     */
    private function searchAdmins(Admin $subject, array $searchTerms, array $known, array &$matches): void
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $excludeIds = $known['admin_ids'];
        $names = $this->collectNameCandidates($searchTerms);
        $emails = $this->collectEmailCandidates($searchTerms);
        $phones = $this->collectPhoneCandidates($searchTerms);
        $companyNames = $this->collectCompanyNameCandidates($searchTerms);

        if ($names === [] && $emails === [] && $phones === [] && $companyNames === []) {
            return;
        }

        $query = Admin::query()
            ->whereIn('type', ['client', 'lead'])
            ->whereNull('is_deleted')
            ->whereNotIn('id', $excludeIds)
            ->where(function ($q) use ($names, $emails, $phones, $companyNames, $like) {
                foreach ($names as $name) {
                    $parts = preg_split('/\s+/', $name) ?: [];
                    if (count($parts) >= 2) {
                        $first = $parts[0];
                        $last = $parts[count($parts) - 1];
                        $q->orWhere(function ($inner) use ($first, $last, $like) {
                            $inner->where('first_name', $like, $first)
                                ->where('last_name', $like, $last);
                        });
                    }
                    $q->orWhereRaw(
                        DB::getDriverName() === 'pgsql'
                            ? "CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) ILIKE ?"
                            : "CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) LIKE ?",
                        ['%' . $name . '%']
                    );
                }

                foreach ($emails as $email) {
                    $q->orWhere('email', $like, $email);
                }

                foreach ($phones as $phone) {
                    $q->orWhere('phone', 'LIKE', '%' . $phone . '%');
                }

                if (Schema::hasColumn('admins', 'company_name')) {
                    foreach ($companyNames as $cn) {
                        $q->orWhere('company_name', $like, '%' . $cn . '%');
                    }
                }
            })
            ->limit(40);

        foreach ($query->get() as $admin) {
            if ($this->isExcludedAdminId((int) $admin->id, $known)) {
                continue;
            }

            $matchedOn = $this->describeAdminMatch($admin, $searchTerms);
            if ($matchedOn === null) {
                continue;
            }

            if (! empty($admin->is_other_party) && $this->isKnownPartyIdentityMatchForAdmin($admin, $known)) {
                continue;
            }

            $roleHint = ! empty($admin->is_other_party) ? 'other_party_record' : ($admin->type ?? 'client');
            $this->addMatch($matches, [
                'source' => 'admin',
                'source_id' => $admin->id,
                'name' => $this->displayName($admin),
                'matched_on' => $matchedOn,
                'context' => sprintf(
                    '%s · %s%s',
                    ucfirst((string) $admin->type),
                    $roleHint === 'other_party_record' ? 'Other party record' : 'Client/lead',
                    $admin->client_id ? ' · Ref ' . $admin->client_id : ''
                ),
                'party_role' => $roleHint,
                'score' => $this->scoreForMatchType($matchedOn),
                'client_id' => $admin->id,
                'client_ref' => $admin->client_id,
            ]);
        }

        // Emails stored in client_emails
        if ($emails !== [] && Schema::hasTable('client_emails')) {
            $emailRows = ClientEmail::query()
                ->whereNotIn('client_id', $excludeIds)
                ->where(function ($q) use ($emails) {
                    foreach ($emails as $email) {
                        $q->orWhereRaw('LOWER(email) = ?', [strtolower($email)]);
                    }
                })
                ->limit(30)
                ->get();

            foreach ($emailRows as $row) {
                $admin = Admin::find($row->client_id);
                if (! $admin || $admin->is_deleted || $this->isExcludedAdminId((int) $admin->id, $known)) {
                    continue;
                }
                if (! empty($admin->is_other_party) && $this->isKnownPartyIdentityMatchForAdmin($admin, $known)) {
                    continue;
                }
                $this->addMatch($matches, [
                    'source' => 'client_email',
                    'source_id' => $row->id,
                    'name' => $this->displayName($admin),
                    'matched_on' => 'email:' . strtolower(trim((string) $row->email)),
                    'context' => 'Email on ' . ucfirst((string) $admin->type) . ' record',
                    'party_role' => $admin->type,
                    'score' => 90,
                    'client_id' => $admin->id,
                    'client_ref' => $admin->client_id,
                ]);
            }
        }
    }

    /**
     * @param  array{subject: array, parties: list<array>, terms: list<string>}  $searchTerms
     * @param  array{admin_ids: list<int>, identity: list<array>}  $known
     * @param  list<array>  $matches
     */
    private function searchConflictPartiesOnOtherClients(Admin $subject, array $searchTerms, array $known, array &$matches): void
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $names = $this->collectNameCandidates($searchTerms);
        $companyNames = $this->collectCompanyNameCandidates($searchTerms);
        $abns = $this->collectAbnCandidates($searchTerms);
        $acns = $this->collectAcnCandidates($searchTerms);

        if ($names === [] && $companyNames === [] && $abns === [] && $acns === []) {
            return;
        }

        $query = ClientConflictParty::query()
            ->where('client_id', '!=', $subject->id)
            ->with('client')
            ->where(function ($q) use ($names, $companyNames, $abns, $acns, $like) {
                foreach ($names as $name) {
                    $parts = preg_split('/\s+/', $name) ?: [];
                    if (count($parts) >= 2) {
                        $first = $parts[0];
                        $last = $parts[count($parts) - 1];
                        $q->orWhere(function ($inner) use ($first, $last, $like) {
                            $inner->where('first_name', $like, $first)
                                ->where('last_name', $like, $last);
                        });
                    }
                    $q->orWhereRaw(
                        DB::getDriverName() === 'pgsql'
                            ? "CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) ILIKE ?"
                            : "CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) LIKE ?",
                        ['%' . $name . '%']
                    );
                }
                foreach ($companyNames as $cn) {
                    $q->orWhere('company_name', $like, '%' . $cn . '%')
                        ->orWhere('trading_name', $like, '%' . $cn . '%');
                }
                foreach ($abns as $abn) {
                    $q->orWhereRaw("REPLACE(REPLACE(COALESCE(abn,''), ' ', ''), '-', '') = ?", [$abn]);
                }
                foreach ($acns as $acn) {
                    $q->orWhereRaw("REPLACE(REPLACE(COALESCE(acn,''), ' ', ''), '-', '') = ?", [$acn]);
                }
            })
            ->limit(40);

        foreach ($query->get() as $party) {
            $owner = $party->client;
            if (! $owner) {
                continue;
            }
            $matchedOn = 'name/company on conflict party';
            $display = $this->partyDisplayName($party);
            foreach ($names as $name) {
                if ($this->namesLooselyMatch($display, $name)) {
                    $matchedOn = 'name:' . $name;
                    break;
                }
            }
            foreach ($abns as $abn) {
                if ($this->digitsOnly($party->abn) === $abn) {
                    $matchedOn = 'abn:' . $abn;
                    break;
                }
            }

            $this->addMatch($matches, [
                'source' => 'conflict_party',
                'source_id' => $party->id,
                'name' => $display,
                'matched_on' => $matchedOn,
                'context' => 'Conflict party on ' . $this->displayName($owner)
                    . ($owner->client_id ? ' (' . $owner->client_id . ')' : ''),
                'party_role' => $party->party_role,
                'score' => $this->scoreForMatchType($matchedOn),
                'client_id' => $owner->id,
                'client_ref' => $owner->client_id,
                'opposing_lead_id' => $party->opposing_lead_id ? (int) $party->opposing_lead_id : null,
            ]);
        }
    }

    /**
     * @param  array{subject: array, parties: list<array>, terms: list<string>}  $searchTerms
     * @param  array{admin_ids: list<int>, identity: list<array>}  $known
     * @param  list<array>  $matches
     */
    private function searchMatterOpposingParties(Admin $subject, array $searchTerms, array $known, array &$matches): void
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $names = $this->collectNameCandidates($searchTerms);
        if ($names === []) {
            return;
        }

        $ownMatterIds = ClientMatter::where('client_id', $subject->id)->pluck('id');

        $query = ClientMatterOpposingParty::query()
            ->with(['clientMatter.matter', 'opposingLead'])
            ->when($ownMatterIds->isNotEmpty(), fn ($q) => $q->whereNotIn('client_matter_id', $ownMatterIds))
            ->where(function ($q) use ($names, $like) {
                foreach ($names as $name) {
                    $q->orWhere('name', $like, '%' . $name . '%');
                }
            })
            ->limit(40);

        foreach ($query->get() as $opp) {
            $matter = $opp->clientMatter;
            $ownerId = $matter?->client_id;
            if ($ownerId && (int) $ownerId === (int) $subject->id) {
                continue;
            }

            $matchedOn = 'name';
            foreach ($names as $name) {
                if ($this->namesLooselyMatch((string) $opp->name, $name)) {
                    $matchedOn = 'name:' . $name;
                    break;
                }
            }

            $matterLabel = $matter?->client_unique_matter_no
                ?? $matter?->matter?->matter_name
                ?? ('Matter #' . $opp->client_matter_id);

            $owner = $ownerId ? Admin::find($ownerId) : null;

            $this->addMatch($matches, [
                'source' => 'matter_opposing_party',
                'source_id' => $opp->id,
                'name' => trim((string) $opp->name) ?: 'Unnamed party',
                'matched_on' => $matchedOn,
                'context' => 'Opposing party on ' . $matterLabel
                    . ($owner ? ' · Client ' . $this->displayName($owner) : ''),
                'party_role' => $opp->party_role,
                'score' => 75,
                'client_id' => $ownerId,
                'client_ref' => $owner?->client_id,
                'matter_id' => $opp->client_matter_id,
                'opposing_lead_id' => $opp->opposing_lead_id ? (int) $opp->opposing_lead_id : null,
            ]);
        }
    }

    /**
     * @param  array{subject: array, parties: list<array>, terms: list<string>}  $searchTerms
     * @param  array{admin_ids: list<int>, identity: list<array>}  $known
     * @param  list<array>  $matches
     */
    private function searchCompanies(Admin $subject, array $searchTerms, array $known, array &$matches): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $companyNames = $this->collectCompanyNameCandidates($searchTerms);
        $abns = $this->collectAbnCandidates($searchTerms);
        $acns = $this->collectAcnCandidates($searchTerms);

        if ($companyNames === [] && $abns === [] && $acns === []) {
            return;
        }

        $excludeAdminIds = $known['admin_ids'];

        $query = Company::query()
            ->whereNotIn('admin_id', $excludeAdminIds)
            ->where(function ($q) use ($companyNames, $abns, $acns, $like) {
                $abnCol = $this->sqlColumn('ABN_number');
                $acnCol = $this->sqlColumn('ACN');

                foreach ($companyNames as $cn) {
                    $q->orWhere('company_name', $like, '%' . $cn . '%')
                        ->orWhere('trading_name', $like, '%' . $cn . '%');
                }
                foreach ($abns as $abn) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(COALESCE({$abnCol}, ''), ' ', ''), '-', '') = ?",
                        [$abn]
                    );
                }
                foreach ($acns as $acn) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(COALESCE({$acnCol}, ''), ' ', ''), '-', '') = ?",
                        [$acn]
                    );
                }
            })
            ->limit(30);

        foreach ($query->get() as $company) {
            $adminId = (int) ($company->admin_id ?? 0);
            if ($adminId <= 0 || $this->isExcludedAdminId($adminId, $known)) {
                continue;
            }

            $admin = Admin::find($adminId);
            if (! $admin || $admin->is_deleted) {
                continue;
            }

            $matchedOn = 'company';
            $digitsAbn = $this->digitsOnly($company->ABN_number);
            foreach ($abns as $abn) {
                if ($digitsAbn === $abn) {
                    $matchedOn = 'abn:' . $abn;
                    break;
                }
            }
            if ($matchedOn === 'company') {
                foreach ($companyNames as $cn) {
                    if (stripos((string) $company->company_name, $cn) !== false
                        || stripos((string) ($company->trading_name ?? ''), $cn) !== false) {
                        $matchedOn = 'company_name:' . $cn;
                        break;
                    }
                }
            }

            $this->addMatch($matches, [
                'source' => 'company',
                'source_id' => $company->id,
                'name' => trim((string) $company->company_name) ?: 'Unnamed company',
                'matched_on' => $matchedOn,
                'context' => 'Company linked to ' . $this->displayName($admin)
                    . ($admin->client_id ? ' (' . $admin->client_id . ')' : ''),
                'party_role' => 'company',
                'score' => $this->scoreForMatchType($matchedOn),
                'client_id' => $admin->id,
                'client_ref' => $admin->client_id,
            ]);
        }
    }

    /**
     * @param  list<array>  $matches
     * @return list<array>
     */
    private function dedupeAndRank(array $matches): array
    {
        $seen = [];
        $unique = [];
        foreach ($matches as $m) {
            $key = ($m['source'] ?? '') . ':' . ($m['source_id'] ?? '') . ':' . ($m['matched_on'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $m;
        }

        usort($unique, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return array_slice($unique, 0, self::MAX_MATCHES);
    }

    /**
     * @param  list<array>  $matches
     */
    private function addMatch(array &$matches, array $match): void
    {
        if (count($matches) >= self::MAX_MATCHES * 2) {
            return;
        }

        if (! empty($match['client_id']) && empty($match['detail_url'])) {
            $match['detail_url'] = url('/clients/detail/' . base64_encode(convert_uuencode((string) $match['client_id'])));
        }

        $matches[] = $match;
    }

    private function displayName(Admin $admin): string
    {
        // Prefer company/personal display helpers on Admin.
        if ($admin->relationLoaded('company') || $admin->is_company) {
            $admin->loadMissing('company');
        }

        $name = trim((string) $admin->company_name_or_personal_name);
        if ($name !== '') {
            return $name;
        }

        $name = trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? ''));

        return $name !== '' ? $name : ('Record #' . $admin->id);
    }

    private function companyNameForAdmin(Admin $admin): ?string
    {
        $admin->loadMissing('company');
        if ($admin->company && ! empty($admin->company->company_name)) {
            return trim((string) $admin->company->company_name);
        }

        if (Schema::hasColumn('admins', 'company_name')) {
            $raw = $admin->getAttributes()['company_name'] ?? null;
            if (! empty($raw)) {
                return trim((string) $raw);
            }
        }

        return null;
    }

    private function partyDisplayName(ClientConflictParty $party): string
    {
        if (($party->party_type ?? 'individual') === 'company') {
            return trim((string) ($party->company_name ?? '')) ?: 'Unnamed company';
        }

        return trim(($party->first_name ?? '') . ' ' . ($party->last_name ?? '')) ?: 'Unnamed';
    }

    /** @return list<string> */
    private function collectEmailsForAdmin(Admin $admin): array
    {
        $emails = [];
        if (! empty($admin->email) && ! str_ends_with(strtolower((string) $admin->email), '@lead.internal')) {
            $emails[] = strtolower(trim((string) $admin->email));
        }

        if (Schema::hasTable('client_emails')) {
            $extra = ClientEmail::where('client_id', $admin->id)->pluck('email');
            foreach ($extra as $e) {
                $e = strtolower(trim((string) $e));
                if ($e !== '' && ! str_ends_with($e, '@lead.internal')) {
                    $emails[] = $e;
                }
            }
        }

        return array_values(array_unique($emails));
    }

    /** @return list<string> */
    private function collectPhonesForAdmin(Admin $admin): array
    {
        $phones = [];
        $normalized = $this->normalizePhone($admin->phone ?? null);
        if ($normalized) {
            $phones[] = $normalized;
        }

        if (Schema::hasTable('client_contacts')) {
            $extra = DB::table('client_contacts')
                ->where('client_id', $admin->id)
                ->pluck('phone');
            foreach ($extra as $p) {
                $n = $this->normalizePhone($p);
                if ($n) {
                    $phones[] = $n;
                }
            }
        }

        return array_values(array_unique($phones));
    }

    /** @param  list<string>  $flat */
    private function pushTerm(array &$flat, ?string $value): void
    {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) < 2) {
            return;
        }
        if ($this->isMeaningfulName($value) === false && ! preg_match('/^\d{8,}$/', $value)) {
            return;
        }
        $flat[] = $value;
    }

    private function digitsOnly(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? $digits : null;
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = $this->digitsOnly($phone);
        if ($digits === null) {
            return null;
        }
        // Keep last 9 digits for AU mobile/landline comparison
        if (strlen($digits) > 9) {
            $digits = substr($digits, -9);
        }

        return strlen($digits) >= 8 ? $digits : null;
    }

    private function namesLooselyMatch(string $a, string $b): bool
    {
        $na = strtolower(preg_replace('/\s+/', ' ', trim($a)) ?? '');
        $nb = strtolower(preg_replace('/\s+/', ' ', trim($b)) ?? '');
        if ($na === '' || $nb === '') {
            return false;
        }
        if ($na === $nb) {
            return true;
        }

        return str_contains($na, $nb) || str_contains($nb, $na);
    }

    /** @return list<string> */
    private function collectNameCandidates(array $searchTerms): array
    {
        $out = [];
        if ($this->isMeaningfulName($searchTerms['subject']['name'] ?? null)) {
            $this->pushTerm($out, $searchTerms['subject']['name'] ?? null);
        }
        foreach ($searchTerms['parties'] as $p) {
            if ($this->isMeaningfulName($p['name'] ?? null)) {
                $this->pushTerm($out, $p['name'] ?? null);
            }
        }

        return array_values(array_unique(array_map(fn ($n) => strtolower($n), $out)));
    }

    /** @return list<string> */
    private function collectEmailCandidates(array $searchTerms): array
    {
        $out = $searchTerms['subject']['emails'] ?? [];
        foreach ($searchTerms['parties'] as $p) {
            $out = array_merge($out, $p['emails'] ?? []);
        }

        return array_values(array_unique(array_filter($out)));
    }

    /** @return list<string> */
    private function collectPhoneCandidates(array $searchTerms): array
    {
        $out = $searchTerms['subject']['phones'] ?? [];
        foreach ($searchTerms['parties'] as $p) {
            $out = array_merge($out, $p['phones'] ?? []);
        }

        return array_values(array_unique(array_filter($out)));
    }

    /** @return list<string> */
    private function collectCompanyNameCandidates(array $searchTerms): array
    {
        $out = [];
        $this->pushTerm($out, $searchTerms['subject']['company_name'] ?? null);
        foreach ($searchTerms['parties'] as $p) {
            $this->pushTerm($out, $p['company_name'] ?? null);
            $this->pushTerm($out, $p['trading_name'] ?? null);
        }

        return array_values(array_unique(array_map(fn ($n) => strtolower($n), $out)));
    }

    /** @return list<string> */
    private function collectAbnCandidates(array $searchTerms): array
    {
        $out = [];
        if (! empty($searchTerms['subject']['abn'])) {
            $out[] = $searchTerms['subject']['abn'];
        }
        foreach ($searchTerms['parties'] as $p) {
            if (! empty($p['abn'])) {
                $out[] = $p['abn'];
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> */
    private function collectAcnCandidates(array $searchTerms): array
    {
        $out = [];
        if (! empty($searchTerms['subject']['acn'])) {
            $out[] = $searchTerms['subject']['acn'];
        }
        foreach ($searchTerms['parties'] as $p) {
            if (! empty($p['acn'])) {
                $out[] = $p['acn'];
            }
        }

        return array_values(array_unique($out));
    }

    private function describeAdminMatch(Admin $admin, array $searchTerms): ?string
    {
        $emails = $this->collectEmailCandidates($searchTerms);
        $adminEmails = $this->collectEmailsForAdmin($admin);
        foreach ($emails as $e) {
            if (in_array($e, $adminEmails, true)) {
                return 'email:' . $e;
            }
        }

        $phones = $this->collectPhoneCandidates($searchTerms);
        $adminPhones = $this->collectPhonesForAdmin($admin);
        foreach ($phones as $p) {
            if (in_array($p, $adminPhones, true)) {
                return 'phone:' . $p;
            }
        }

        $names = $this->collectNameCandidates($searchTerms);
        $display = strtolower($this->displayName($admin));
        foreach ($names as $name) {
            if ($this->namesLooselyMatch($display, $name)) {
                return 'name:' . $name;
            }
        }

        $companyNames = $this->collectCompanyNameCandidates($searchTerms);
        $adminCompany = strtolower((string) ($this->companyNameForAdmin($admin) ?? ''));
        foreach ($companyNames as $cn) {
            if ($adminCompany !== '' && (str_contains($adminCompany, $cn) || str_contains($cn, $adminCompany))) {
                return 'company_name:' . $cn;
            }
        }

        return null;
    }

    private function scoreForMatchType(string $matchedOn): int
    {
        if (str_starts_with($matchedOn, 'email:')) {
            return 95;
        }
        if (str_starts_with($matchedOn, 'abn:') || str_starts_with($matchedOn, 'acn:')) {
            return 98;
        }
        if (str_starts_with($matchedOn, 'phone:')) {
            return 85;
        }
        if (str_starts_with($matchedOn, 'name:')) {
            return 70;
        }
        if (str_starts_with($matchedOn, 'company_name:')) {
            return 80;
        }

        return 50;
    }

    /**
     * Validate solicitor-selected outcome against server search results.
     *
     * @param  array{
     *     match_count?: int,
     *     informational_count?: int
     * }  $result
     * @param  array{
     *     outcome_notes?: string,
     *     consent_obtained?: bool,
     *     consent_notes?: string,
     *     force_clear?: bool
     * }  $options
     */
    public function validateOutcomeAgainstResults(string $outcome, array $result, array $options = []): ?string
    {
        $matchCount = (int) ($result['match_count'] ?? 0);
        $outcomeNotes = trim((string) ($options['outcome_notes'] ?? ''));
        $consentObtained = (bool) ($options['consent_obtained'] ?? false);
        $consentNotes = trim((string) ($options['consent_notes'] ?? ''));
        $forceClear = (bool) ($options['force_clear'] ?? false);

        if ($outcome === 'clear') {
            if ($matchCount > 0 && ! $forceClear) {
                return 'Cannot save Clear while potential conflicts exist. Record Conflict found, or document an override with force clear and detailed notes.';
            }
            if ($matchCount > 0 && $forceClear && strlen($outcomeNotes) < 20) {
                return 'A detailed note (at least 20 characters) is required when clearing despite potential conflicts.';
            }
        }

        if ($outcome === 'waived') {
            if (! $consentObtained) {
                return 'Consent obtained must be checked when outcome is Waived with consent.';
            }
            if ($consentNotes === '') {
                return 'Consent notes are required when outcome is Waived with consent (who consented, form used, etc.).';
            }
            if ($matchCount > 0 && strlen($outcomeNotes) < 10) {
                return 'Outcome notes explaining the waiver are required when potential conflicts exist.';
            }
        }

        if ($outcome === 'conflict_found' && $outcomeNotes === '') {
            return 'Notes are required when recording a conflict found outcome.';
        }

        return null;
    }

    /**
     * @param  array{subject?: array, parties?: list<array>, terms?: list<string>}  $searchTerms
     */
    public function buildSearchHash(array $searchTerms): string
    {
        return hash('sha256', json_encode($searchTerms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
