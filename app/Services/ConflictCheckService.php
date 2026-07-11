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
     * @return array{search_terms: array, matches: list<array>, suggested_outcome: string, match_count: int}
     */
    public function run(Admin $client): array
    {
        $parties = ClientConflictParty::where('client_id', $client->id)
            ->with(['phones', 'emails', 'opposingLead'])
            ->orderBy('sort_order')
            ->get();

        $searchTerms = $this->buildSearchTerms($client, $parties);
        $matches = [];

        $this->searchAdmins($client, $searchTerms, $matches);
        $this->searchConflictPartiesOnOtherClients($client, $searchTerms, $matches);
        $this->searchMatterOpposingParties($client, $searchTerms, $matches);
        $this->searchCompanies($client, $searchTerms, $matches);

        $matches = $this->dedupeAndRank($matches);
        $matchCount = count($matches);

        return [
            'search_terms' => $searchTerms,
            'matches' => $matches,
            'suggested_outcome' => $matchCount === 0 ? 'clear' : 'pending',
            'match_count' => $matchCount,
        ];
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

        if ($client->is_company && $client->relationLoaded('company') === false) {
            $client->load('company');
        }
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
     * @param  list<array>  $matches
     */
    private function searchAdmins(Admin $subject, array $searchTerms, array &$matches): void
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $excludeIds = [(int) $subject->id];

        // Do not flag other-party records already linked on this client's conflict list.
        foreach ($searchTerms['parties'] ?? [] as $pt) {
            $leadId = (int) ($pt['opposing_lead_id'] ?? 0);
            if ($leadId > 0) {
                $excludeIds[] = $leadId;
            }
        }
        $excludeIds = array_values(array_unique($excludeIds));

        // Linked other-party leads are expected; still flag if they are also a real client elsewhere.
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
            $matchedOn = $this->describeAdminMatch($admin, $searchTerms);
            if ($matchedOn === null) {
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
                if (! $admin || $admin->is_deleted) {
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
     * @param  list<array>  $matches
     */
    private function searchConflictPartiesOnOtherClients(Admin $subject, array $searchTerms, array &$matches): void
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
            ]);
        }
    }

    /**
     * @param  array{subject: array, parties: list<array>, terms: list<string>}  $searchTerms
     * @param  list<array>  $matches
     */
    private function searchMatterOpposingParties(Admin $subject, array $searchTerms, array &$matches): void
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
            ]);
        }
    }

    /**
     * @param  array{subject: array, parties: list<array>, terms: list<string>}  $searchTerms
     * @param  list<array>  $matches
     */
    private function searchCompanies(Admin $subject, array $searchTerms, array &$matches): void
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

        $query = Company::query()
            ->where('admin_id', '!=', $subject->id)
            ->where(function ($q) use ($companyNames, $abns, $acns, $like) {
                foreach ($companyNames as $cn) {
                    $q->orWhere('company_name', $like, '%' . $cn . '%')
                        ->orWhere('trading_name', $like, '%' . $cn . '%');
                }
                foreach ($abns as $abn) {
                    $q->orWhereRaw("REPLACE(REPLACE(COALESCE(ABN_number,''), ' ', ''), '-', '') = ?", [$abn]);
                }
                foreach ($acns as $acn) {
                    $q->orWhereRaw("REPLACE(REPLACE(COALESCE(ACN,''), ' ', ''), '-', '') = ?", [$acn]);
                }
            })
            ->limit(30);

        foreach ($query->get() as $company) {
            $admin = Admin::find($company->admin_id);
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
        $this->pushTerm($out, $searchTerms['subject']['name'] ?? null);
        foreach ($searchTerms['parties'] as $p) {
            $this->pushTerm($out, $p['name'] ?? null);
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
}
