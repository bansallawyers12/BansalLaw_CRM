<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\ClientMatterOpposingParty;
use Illuminate\Support\Facades\Schema;

class OpposingPartyHelper
{
    /**
     * Parse opposing_parties_json payload into normalized rows.
     *
     * @return list<array{opposing_lead_id: int|null, name: string, party_role: string, rep_firm: string, rep_name: string, rep_email: string, rep_phone: string, rep_notes: string}>
     */
    public static function parseJsonPayload(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new \InvalidArgumentException('Other parties data must be valid JSON.');
        }

        $rows = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }

            $leadId = isset($row['opposing_lead_id']) ? (int) $row['opposing_lead_id'] : 0;
            $partyRole = isset($row['party_role']) ? trim((string) $row['party_role']) : '';
            $name = trim((string) ($row['name'] ?? ''));

            $hasAny = $leadId > 0 || $partyRole !== '' || $name !== '';
            if (! $hasAny) {
                continue;
            }

            if ($partyRole === '') {
                throw new \InvalidArgumentException('Each other party must have a role selected.');
            }

            if ($leadId <= 0) {
                if ($name === '') {
                    throw new \InvalidArgumentException('Each other party must be selected from the Other Parties list or include a name.');
                }

                $rows[] = self::normalizeRow(null, $name, $partyRole, $row);
                if (count($rows) >= 20) {
                    break;
                }

                continue;
            }

            $lead = Admin::query()->with('company')->find($leadId);
            if (! $lead) {
                throw new \InvalidArgumentException('One of the selected other parties could not be found.');
            }

            if (Schema::hasColumn('admins', 'is_other_party') && ! (bool) ($lead->is_other_party ?? false)) {
                throw new \InvalidArgumentException('One of the selected parties is not flagged as an other party.');
            }

            if ($name === '') {
                if ((bool) ($lead->is_company ?? false)) {
                    $name = trim((string) ($lead->company_name ?? ''))
                        ?: trim($lead->first_name . ' ' . $lead->last_name);
                } else {
                    $name = trim($lead->first_name . ' ' . $lead->last_name);
                }
            }

            $rows[] = self::normalizeRow($leadId, $name, $partyRole, $row);
            if (count($rows) >= 20) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{opposing_lead_id: int|null, name: string, party_role: string, rep_firm: string, rep_name: string, rep_email: string, rep_phone: string, rep_notes: string}
     */
    private static function normalizeRow(?int $leadId, string $name, string $partyRole, array $row): array
    {
        return [
            'opposing_lead_id' => $leadId,
            'name' => $name,
            'party_role' => $partyRole,
            'rep_firm' => isset($row['rep_firm']) ? trim((string) $row['rep_firm']) : '',
            'rep_name' => isset($row['rep_name']) ? trim((string) $row['rep_name']) : '',
            'rep_email' => isset($row['rep_email']) ? trim((string) $row['rep_email']) : '',
            'rep_phone' => isset($row['rep_phone']) ? trim((string) $row['rep_phone']) : '',
            'rep_notes' => isset($row['rep_notes']) ? trim((string) $row['rep_notes']) : '',
        ];
    }

    /**
     * Replace all opposing parties on a matter.
     *
     * @param  list<array{opposing_lead_id: int|null, name: string, party_role: string, rep_firm: string, rep_name: string, rep_email: string, rep_phone: string, rep_notes: string}>  $parties
     */
    public static function syncForMatter(int $clientMatterId, array $parties): void
    {
        if (! Schema::hasTable('client_matter_opposing_parties')) {
            return;
        }

        ClientMatterOpposingParty::query()->where('client_matter_id', $clientMatterId)->delete();

        foreach ($parties as $i => $party) {
            $data = [
                'client_matter_id' => $clientMatterId,
                'name' => $party['name'],
                'party_role' => $party['party_role'],
                'sort_order' => $i,
            ];

            if (Schema::hasColumn('client_matter_opposing_parties', 'opposing_lead_id')) {
                $leadId = $party['opposing_lead_id'] ?? null;
                $data['opposing_lead_id'] = ($leadId !== null && (int) $leadId > 0) ? (int) $leadId : null;
            }
            foreach (['rep_firm', 'rep_name', 'rep_email', 'rep_phone', 'rep_notes'] as $repCol) {
                if (Schema::hasColumn('client_matter_opposing_parties', $repCol)) {
                    $val = $party[$repCol] ?? '';
                    $data[$repCol] = $val !== '' ? $val : null;
                }
            }

            ClientMatterOpposingParty::create($data);
        }
    }

    /**
     * @return list<string>
     */
    public static function opposingPartySelectColumns(): array
    {
        $cols = ['id', 'name', 'party_role', 'sort_order'];
        if (Schema::hasColumn('client_matter_opposing_parties', 'opposing_lead_id')) {
            $cols[] = 'opposing_lead_id';
        }
        foreach (['rep_firm', 'rep_name', 'rep_email', 'rep_phone', 'rep_notes'] as $c) {
            if (Schema::hasColumn('client_matter_opposing_parties', $c)) {
                $cols[] = $c;
            }
        }

        return $cols;
    }
}
