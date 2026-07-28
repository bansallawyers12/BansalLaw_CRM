<?php

use App\Models\ClientConflictParty;
use App\Models\ClientMatter;
use App\Support\OpposingPartyHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move legacy client-wide conflict parties onto each client's latest active matter
     * and sync them into client_matter_opposing_parties.
     */
    public function up(): void
    {
        if (! Schema::hasTable('client_conflict_parties') || ! Schema::hasTable('client_matters')) {
            return;
        }

        $legacyRows = ClientConflictParty::query()
            ->whereNull('client_matter_id')
            ->orderBy('client_id')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('client_id');

        foreach ($legacyRows as $clientId => $parties) {
            $clientMatterId = ClientMatter::query()
                ->where('client_id', (int) $clientId)
                ->where('matter_status', 1)
                ->orderByDesc('id')
                ->value('id');

            if (! $clientMatterId) {
                continue;
            }

            $clientMatterId = (int) $clientMatterId;
            $payload = [];

            foreach ($parties as $party) {
                if (($party->party_type ?? 'individual') === 'company') {
                    $name = trim((string) ($party->company_name ?? ''));
                } else {
                    $name = trim(trim((string) ($party->first_name ?? '')) . ' ' . trim((string) ($party->last_name ?? '')));
                }

                if ($name === '') {
                    continue;
                }

                $payload[] = [
                    'opposing_lead_id' => $party->opposing_lead_id ?? null,
                    'name'             => $name,
                    'party_role'       => (string) ($party->party_role ?? 'other'),
                    'rep_firm'         => (string) ($party->rep_firm_name ?? ''),
                    'rep_name'         => (string) ($party->rep_name ?? ''),
                    'rep_email'        => (string) ($party->rep_email ?? ''),
                    'rep_phone'        => (string) ($party->rep_phone ?? ''),
                    'rep_notes'        => (string) ($party->notes ?? ''),
                ];
            }

            if ($payload === []) {
                continue;
            }

            ClientConflictParty::query()
                ->where('client_id', (int) $clientId)
                ->whereNull('client_matter_id')
                ->update(['client_matter_id' => $clientMatterId]);

            if (Schema::hasTable('client_matter_opposing_parties')) {
                $hasMatterParties = \App\Models\ClientMatterOpposingParty::query()
                    ->where('client_matter_id', $clientMatterId)
                    ->exists();

                if (! $hasMatterParties) {
                    OpposingPartyHelper::syncForMatter($clientMatterId, $payload);
                }
            }
        }
    }

    public function down(): void
    {
        // Non-destructive data migration; no rollback.
    }
};
