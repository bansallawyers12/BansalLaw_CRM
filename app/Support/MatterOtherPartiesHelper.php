<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\ClientConflictParty;
use App\Models\ClientMatter;
use App\Models\ClientMatterOpposingParty;
use App\Models\ConflictPartyContact;
use App\Models\ConflictPartyEmail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MatterOtherPartiesHelper
{
    /**
     * Resolve the active client matter id from request / URL context.
     *
     * When an explicit matter id or matter ref is provided but invalid for this client,
     * returns null (does not silently fall back to another matter).
     */
    public static function resolveClientMatterId(int $clientId, ?int $requestedId, ?string $matterRef): ?int
    {
        if ($requestedId !== null && $requestedId > 0) {
            $exists = ClientMatter::query()
                ->where('id', $requestedId)
                ->where('client_id', $clientId)
                ->exists();

            return $exists ? $requestedId : null;
        }

        $matterRef = trim((string) ($matterRef ?? ''));
        if ($matterRef !== '') {
            $id = ClientMatter::query()
                ->where('client_id', $clientId)
                ->where('client_unique_matter_no', $matterRef)
                ->value('id');

            return $id ? (int) $id : null;
        }

        $fallbackId = ClientMatter::query()
            ->where('client_id', $clientId)
            ->where('matter_status', 1)
            ->orderByDesc('id')
            ->value('id');

        return $fallbackId ? (int) $fallbackId : null;
    }

    /**
     * Load other parties for the conflict card (matter-scoped when a matter exists).
     *
     * Source of truth order for a matter:
     * 1) client_matter_opposing_parties
     * 2) client_conflict_parties scoped to that matter
     * Never falls back to client-wide (null client_matter_id) parties when a matter is active.
     *
     * @return Collection<int, object|ClientConflictParty>
     */
    public static function loadDisplayParties(int $clientId, ?int $clientMatterId): Collection
    {
        if ($clientMatterId) {
            if (Schema::hasTable('client_matter_opposing_parties')) {
                $opposing = ClientMatterOpposingParty::query()
                    ->where('client_matter_id', $clientMatterId)
                    ->with(['opposingLead.company'])
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();

                if ($opposing->isNotEmpty()) {
                    return $opposing->map(fn (ClientMatterOpposingParty $row) => self::opposingPartyToDisplay($row));
                }
            }

            if (Schema::hasTable('client_conflict_parties')) {
                return ClientConflictParty::query()
                    ->where('client_id', $clientId)
                    ->where('client_matter_id', $clientMatterId)
                    ->with(['phones', 'emails', 'opposingLead.company'])
                    ->orderBy('sort_order')
                    ->get();
            }

            return collect();
        }

        if (! Schema::hasTable('client_conflict_parties')) {
            return collect();
        }

        return ClientConflictParty::query()
            ->where('client_id', $clientId)
            ->whereNull('client_matter_id')
            ->with(['phones', 'emails', 'opposingLead.company'])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Load parties for automated conflict search (same source-of-truth order as display).
     *
     * @return Collection<int, ClientConflictParty>
     */
    public static function loadForConflictSearch(int $clientId, ?int $clientMatterId): Collection
    {
        if ($clientMatterId) {
            if (Schema::hasTable('client_matter_opposing_parties')) {
                $opposing = ClientMatterOpposingParty::query()
                    ->where('client_matter_id', $clientMatterId)
                    ->with(['opposingLead.company'])
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();

                if ($opposing->isNotEmpty()) {
                    return $opposing->map(
                        fn (ClientMatterOpposingParty $row) => self::opposingPartyToConflictModel($row, $clientId, $clientMatterId)
                    );
                }
            }

            if (Schema::hasTable('client_conflict_parties')) {
                return ClientConflictParty::query()
                    ->where('client_id', $clientId)
                    ->where('client_matter_id', $clientMatterId)
                    ->with(['phones', 'emails', 'opposingLead.company'])
                    ->orderBy('sort_order')
                    ->get();
            }

            return collect();
        }

        if (! Schema::hasTable('client_conflict_parties')) {
            return collect();
        }

        return ClientConflictParty::query()
            ->where('client_id', $clientId)
            ->whereNull('client_matter_id')
            ->with(['phones', 'emails', 'opposingLead.company'])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Persist other parties for the active matter (or client-level when no matter).
     *
     * @param  list<array{opposing_lead_id: int|null, name: string, party_role: string, rep_firm: string, rep_name: string, rep_email: string, rep_phone: string, rep_notes: string}>  $parties
     */
    public static function saveParties(int $clientId, ?int $clientMatterId, array $parties): int
    {
        if ($clientMatterId && Schema::hasTable('client_matter_opposing_parties')) {
            DB::transaction(function () use ($clientId, $clientMatterId, $parties) {
                OpposingPartyHelper::syncForMatter($clientMatterId, $parties);
                self::syncConflictPartiesForMatter($clientId, $clientMatterId, $parties);
            });

            return count($parties);
        }

        self::syncConflictPartiesClientLevel($clientId, $parties);

        return count($parties);
    }

    /**
     * Keep conflict-party rows aligned after matter assignee / edit modal saves opposing parties.
     *
     * @param  list<array{opposing_lead_id: int|null, name: string, party_role: string, rep_firm: string, rep_name: string, rep_email: string, rep_phone: string, rep_notes: string}>  $parties
     */
    public static function syncConflictPartiesAfterMatterSave(int $clientId, int $clientMatterId, array $parties): void
    {
        if (! Schema::hasTable('client_conflict_parties')) {
            return;
        }

        self::syncConflictPartiesForMatter($clientId, $clientMatterId, $parties);
    }

    /**
     * @param  list<array{opposing_lead_id: int|null, name: string, party_role: string, rep_firm: string, rep_name: string, rep_email: string, rep_phone: string, rep_notes: string}>  $parties
     */
    private static function syncConflictPartiesForMatter(int $clientId, int $clientMatterId, array $parties): void
    {
        if (! Schema::hasTable('client_conflict_parties')) {
            return;
        }

        $existingIds = ClientConflictParty::query()
            ->where('client_id', $clientId)
            ->where('client_matter_id', $clientMatterId)
            ->pluck('id');

        self::deleteConflictPartyRows($existingIds);

        foreach ($parties as $i => $party) {
            self::createConflictPartyRow($clientId, $clientMatterId, $party, $i);
        }
    }

    /**
     * @param  list<array{opposing_lead_id: int|null, name: string, party_role: string, rep_firm: string, rep_name: string, rep_email: string, rep_phone: string, rep_notes: string}>  $parties
     */
    private static function syncConflictPartiesClientLevel(int $clientId, array $parties): void
    {
        if (! Schema::hasTable('client_conflict_parties')) {
            return;
        }

        $existingIds = ClientConflictParty::query()
            ->where('client_id', $clientId)
            ->whereNull('client_matter_id')
            ->pluck('id');

        self::deleteConflictPartyRows($existingIds);

        foreach ($parties as $i => $party) {
            self::createConflictPartyRow($clientId, null, $party, $i);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int|string>  $existingIds
     */
    private static function deleteConflictPartyRows($existingIds): void
    {
        if ($existingIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('conflict_party_contacts')) {
            ConflictPartyContact::query()->whereIn('conflict_party_id', $existingIds)->delete();
        }
        if (Schema::hasTable('conflict_party_emails')) {
            ConflictPartyEmail::query()->whereIn('conflict_party_id', $existingIds)->delete();
        }
        ClientConflictParty::query()->whereIn('id', $existingIds)->delete();
    }

    /**
     * @param  array{opposing_lead_id: int|null, name: string, party_role: string, rep_firm: string, rep_name: string, rep_email: string, rep_phone: string, rep_notes: string}  $party
     */
    private static function createConflictPartyRow(int $clientId, ?int $clientMatterId, array $party, int $sortOrder): ClientConflictParty
    {
        $leadId = isset($party['opposing_lead_id']) ? (int) $party['opposing_lead_id'] : 0;
        $lead = ($leadId > 0) ? Admin::query()->with('company')->find($leadId) : null;
        $payloadName = trim((string) ($party['name'] ?? ''));

        $data = [
            'client_id'     => $clientId,
            'party_role'    => $party['party_role'],
            'sort_order'    => $sortOrder,
            'created_by'    => Auth::id(),
            'rep_firm_name' => ($party['rep_firm'] ?? '') !== '' ? $party['rep_firm'] : null,
            'rep_name'      => ($party['rep_name'] ?? '') !== '' ? $party['rep_name'] : null,
            'rep_email'     => ($party['rep_email'] ?? '') !== '' ? $party['rep_email'] : null,
            'rep_phone'     => ($party['rep_phone'] ?? '') !== '' ? $party['rep_phone'] : null,
            'notes'         => ($party['rep_notes'] ?? '') !== '' ? $party['rep_notes'] : null,
            'country'       => 'Australia',
        ];

        if (Schema::hasColumn('client_conflict_parties', 'client_matter_id')) {
            $data['client_matter_id'] = $clientMatterId;
        }

        if (Schema::hasColumn('client_conflict_parties', 'opposing_lead_id')) {
            $data['opposing_lead_id'] = ($leadId > 0) ? $leadId : null;
        }

        if ($lead && (bool) ($lead->is_company ?? false)) {
            $data['party_type'] = 'company';
            $data['company_name'] = $payloadName
                ?: trim((string) ($lead->company_name ?? ''))
                ?: trim($lead->first_name . ' ' . $lead->last_name)
                ?: null;
            $data['first_name'] = null;
            $data['last_name'] = null;
        } elseif ($lead) {
            $data['party_type'] = 'individual';
            if ($payloadName !== '') {
                $parts = preg_split('/\s+/', $payloadName, 2);
                $data['first_name'] = $parts[0] ?? $lead->first_name;
                $data['last_name'] = $parts[1] ?? ($lead->last_name ?: null);
            } else {
                $data['first_name'] = $lead->first_name;
                $data['last_name'] = $lead->last_name;
            }
        } else {
            $parts = preg_split('/\s+/', $payloadName, 2);
            $data['party_type'] = 'individual';
            $data['first_name'] = $parts[0] ?? null;
            $data['last_name'] = $parts[1] ?? null;
        }

        $row = ClientConflictParty::create($data);

        if ($lead && $lead->phone && Schema::hasTable('conflict_party_contacts')) {
            ConflictPartyContact::create([
                'conflict_party_id' => $row->id,
                'contact_type'      => 'Mobile',
                'country_code'      => '+61',
                'phone'             => $lead->phone,
            ]);
        }

        if ($lead && $lead->email && ! str_ends_with((string) $lead->email, '@lead.internal') && Schema::hasTable('conflict_party_emails')) {
            ConflictPartyEmail::create([
                'conflict_party_id' => $row->id,
                'email_type'        => 'Personal',
                'email'             => $lead->email,
            ]);
        }

        return $row;
    }

    private static function opposingPartyToDisplay(ClientMatterOpposingParty $party): object
    {
        $name = trim((string) ($party->name ?? ''));
        $lead = $party->opposingLead;
        if ($lead) {
            $lead->loadMissing('company');
        }
        $isCompany = $lead && (bool) ($lead->is_company ?? false);

        if ($isCompany) {
            return (object) [
                'opposing_lead_id' => $party->opposing_lead_id,
                'party_type'       => 'company',
                'company_name'     => $name !== '' ? $name : (trim((string) ($lead->company_name ?? '')) ?: 'Unnamed company'),
                'first_name'       => null,
                'last_name'        => null,
                'party_role'       => $party->party_role,
                'rep_firm_name'    => $party->rep_firm,
                'rep_name'         => $party->rep_name,
                'rep_email'        => $party->rep_email,
                'rep_phone'        => $party->rep_phone,
                'notes'            => $party->rep_notes,
            ];
        }

        if ($name === '' && $lead) {
            $name = trim($lead->first_name . ' ' . $lead->last_name);
        }

        $parts = preg_split('/\s+/', $name, 2);

        return (object) [
            'opposing_lead_id' => $party->opposing_lead_id,
            'party_type'       => 'individual',
            'company_name'     => null,
            'first_name'       => $parts[0] ?? null,
            'last_name'        => $parts[1] ?? null,
            'party_role'       => $party->party_role,
            'rep_firm_name'    => $party->rep_firm,
            'rep_name'         => $party->rep_name,
            'rep_email'        => $party->rep_email,
            'rep_phone'        => $party->rep_phone,
            'notes'            => $party->rep_notes,
        ];
    }

    private static function opposingPartyToConflictModel(
        ClientMatterOpposingParty $party,
        int $clientId,
        int $clientMatterId
    ): ClientConflictParty {
        $display = self::opposingPartyToDisplay($party);
        $model = new ClientConflictParty([
            'client_id'        => $clientId,
            'client_matter_id' => $clientMatterId,
            'opposing_lead_id' => $display->opposing_lead_id,
            'party_type'       => $display->party_type,
            'party_role'       => $display->party_role,
            'first_name'       => $display->first_name,
            'last_name'        => $display->last_name,
            'company_name'     => $display->company_name,
            'rep_firm_name'    => $display->rep_firm_name,
            'rep_name'         => $display->rep_name,
            'rep_email'        => $display->rep_email,
            'rep_phone'        => $display->rep_phone,
            'notes'            => $display->notes,
            'sort_order'       => $party->sort_order,
        ]);
        $model->setRelation('phones', collect());
        $model->setRelation('emails', collect());
        if ($party->relationLoaded('opposingLead')) {
            $model->setRelation('opposingLead', $party->opposingLead);
        }

        return $model;
    }
}
