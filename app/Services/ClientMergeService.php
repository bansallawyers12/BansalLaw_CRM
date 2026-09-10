<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migrates related CRM rows when merging one client/lead (source) into another (survivor).
 */
class ClientMergeService
{
    /**
     * Remap client/lead foreign keys from $fromId onto $toId.
     * Caller must run inside an open DB transaction.
     */
    public function migrateRelatedRecords(int $fromId, int $toId): void
    {
        $this->mergeCompanyRows($fromId, $toId);
        $this->remapOpposingLeadReferences($fromId, $toId);

        $tablesToMigrate = [
            'client_matters' => ['client_id'],
            'account_client_receipts' => ['client_id'],
            'account_all_invoice_receipts' => ['client_id'],
            'client_emails' => ['client_id', 'admin_id'],
            'client_contacts' => ['client_id', 'admin_id'],
            'client_addresses' => ['client_id', 'admin_id'],
            'client_conflict_checks' => ['client_id'],
            'client_conflict_parties' => ['client_id'],
            'client_court_hearings' => ['client_id'],
            'client_legal_forms' => ['client_id'],
            'client_matter_tasks' => ['client_id'],
            'activities_logs' => ['client_id'],
            'notes' => ['client_id', 'lead_id'],
            'note_attachments' => ['client_id'],
            'documents' => ['client_id', 'lead_id'],
            'appointments' => ['client_id'],
            'booking_appointments' => ['client_id', 'user_id'],
            'quotations' => ['client_id'],
            'email_logs' => ['client_id'],
            'checkin_logs' => ['client_id'],
            'front_desk_check_ins' => ['client_id', 'admin_id', 'lead_id'],
            'company_directors' => ['director_client_id'],
            'companies' => ['admin_id', 'contact_person_id'],
            'email_verifications' => ['client_id'],
            'phone_verifications' => ['client_id'],
            'personal_document_types' => ['client_id'],
            'visa_document_types' => ['client_id'],
            'sms_logs' => ['client_id'],
            'staff_calendar_events' => ['client_id'],
            'client_access_grants' => ['admin_id'],
            'client_tr_references' => ['client_id'],
            'client_visitor_references' => ['client_id'],
            'client_student_references' => ['client_id'],
            'client_pr_references' => ['client_id'],
            'client_employer_sponsored_references' => ['client_id'],
            'lead_tr_references' => ['lead_id'],
            'lead_visitor_references' => ['lead_id'],
            'lead_student_references' => ['lead_id'],
            'lead_pr_references' => ['lead_id'],
            'lead_employer_sponsored_references' => ['lead_id'],
            'lead_tr_reminders' => ['lead_id'],
            'lead_visitor_reminders' => ['lead_id'],
            'lead_student_reminders' => ['lead_id'],
            'lead_pr_reminders' => ['lead_id'],
            'lead_employer_sponsored_reminders' => ['lead_id'],
        ];

        foreach ($tablesToMigrate as $tableName => $cols) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($cols as $col) {
                if (! Schema::hasColumn($tableName, $col)) {
                    continue;
                }

                if ($tableName === 'companies' && $col === 'admin_id') {
                    // Handled in mergeCompanyRows (unique admin_id).
                    continue;
                }

                if ($tableName === 'client_conflict_parties' && $col === 'client_id') {
                    $this->remapConflictPartyClientId($fromId, $toId);
                    continue;
                }

                if ($this->isClientReferenceTable($tableName)) {
                    $this->remapUniqueClientMatterRows($tableName, 'client_id', $fromId, $toId);
                    continue;
                }

                if ($this->isLeadReferenceOrReminderTable($tableName)) {
                    $matterCol = Schema::hasColumn($tableName, 'matter_id') ? 'matter_id' : null;
                    $this->remapUniqueLeadMatterRows($tableName, 'lead_id', $matterCol, $fromId, $toId);
                    continue;
                }

                DB::table($tableName)->where($col, $fromId)->update([$col => $toId]);
            }
        }
    }

    /**
     * companies.admin_id is unique (1:1). Merge source company into survivor when both exist.
     */
    protected function mergeCompanyRows(int $fromId, int $toId): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $source = DB::table('companies')->where('admin_id', $fromId)->first();
        if (! $source) {
            return;
        }

        $survivor = DB::table('companies')->where('admin_id', $toId)->first();

        if (! $survivor) {
            DB::table('companies')->where('id', $source->id)->update(['admin_id' => $toId]);

            return;
        }

        $updates = [];
        foreach ([
            'company_name', 'trading_name', 'ABN_number', 'ACN', 'company_type',
            'company_website', 'contact_person_id', 'contact_person_position',
        ] as $field) {
            if (! property_exists($survivor, $field) || ! property_exists($source, $field)) {
                continue;
            }
            $toVal = $survivor->$field;
            $fromVal = $source->$field;
            if ((is_null($toVal) || trim((string) $toVal) === '')
                && (! is_null($fromVal) && trim((string) $fromVal) !== '')) {
                $updates[$field] = $fromVal;
            }
        }

        if ($updates !== []) {
            DB::table('companies')->where('id', $survivor->id)->update($updates);
        }

        DB::table('companies')->where('id', $source->id)->delete();
    }

    /**
     * Remap rows where the merged person is the opposing party on other matters.
     */
    protected function remapOpposingLeadReferences(int $fromId, int $toId): void
    {
        if (Schema::hasTable('client_conflict_parties')
            && Schema::hasColumn('client_conflict_parties', 'opposing_lead_id')) {
            $rows = DB::table('client_conflict_parties')->where('opposing_lead_id', $fromId)->get();
            foreach ($rows as $row) {
                $query = DB::table('client_conflict_parties')
                    ->where('opposing_lead_id', $toId)
                    ->where('id', '!=', $row->id);

                if (! empty($row->client_matter_id)) {
                    $query->where('client_matter_id', $row->client_matter_id);
                } else {
                    $query->whereNull('client_matter_id')
                        ->where('client_id', $row->client_id);
                }

                if ($query->exists()) {
                    DB::table('client_conflict_parties')->where('id', $row->id)->delete();
                } else {
                    DB::table('client_conflict_parties')
                        ->where('id', $row->id)
                        ->update(['opposing_lead_id' => $toId]);
                }
            }
        }

        if (Schema::hasTable('client_matter_opposing_parties')
            && Schema::hasColumn('client_matter_opposing_parties', 'opposing_lead_id')) {
            $rows = DB::table('client_matter_opposing_parties')->where('opposing_lead_id', $fromId)->get();
            foreach ($rows as $row) {
                $exists = DB::table('client_matter_opposing_parties')
                    ->where('opposing_lead_id', $toId)
                    ->where('client_matter_id', $row->client_matter_id)
                    ->where('id', '!=', $row->id)
                    ->exists();

                if ($exists) {
                    DB::table('client_matter_opposing_parties')->where('id', $row->id)->delete();
                } else {
                    DB::table('client_matter_opposing_parties')
                        ->where('id', $row->id)
                        ->update(['opposing_lead_id' => $toId]);
                }
            }
        }
    }

    /**
     * Avoid unique (client_matter_id|client_id, opposing_lead_id) collisions when remapping client_id.
     */
    protected function remapConflictPartyClientId(int $fromId, int $toId): void
    {
        $rows = DB::table('client_conflict_parties')->where('client_id', $fromId)->get();
        foreach ($rows as $row) {
            $query = DB::table('client_conflict_parties')
                ->where('client_id', $toId)
                ->where('id', '!=', $row->id);

            if (! empty($row->opposing_lead_id)) {
                $query->where('opposing_lead_id', $row->opposing_lead_id);
            } else {
                $query->whereNull('opposing_lead_id');
            }

            if (! empty($row->client_matter_id)) {
                $query->where('client_matter_id', $row->client_matter_id);
            } else {
                $query->whereNull('client_matter_id');
            }

            if ($query->exists()) {
                DB::table('client_conflict_parties')->where('id', $row->id)->delete();
            } else {
                DB::table('client_conflict_parties')
                    ->where('id', $row->id)
                    ->update(['client_id' => $toId]);
            }
        }
    }

    protected function isClientReferenceTable(string $tableName): bool
    {
        return in_array($tableName, [
            'client_tr_references',
            'client_visitor_references',
            'client_student_references',
            'client_pr_references',
            'client_employer_sponsored_references',
        ], true);
    }

    protected function isLeadReferenceOrReminderTable(string $tableName): bool
    {
        return in_array($tableName, [
            'lead_tr_references',
            'lead_visitor_references',
            'lead_student_references',
            'lead_pr_references',
            'lead_employer_sponsored_references',
            'lead_tr_reminders',
            'lead_visitor_reminders',
            'lead_student_reminders',
            'lead_pr_reminders',
            'lead_employer_sponsored_reminders',
        ], true);
    }

    /**
     * Unique (client_id, client_matter_id) — drop source row when survivor already has the pair.
     */
    protected function remapUniqueClientMatterRows(string $table, string $clientCol, int $fromId, int $toId): void
    {
        if (! Schema::hasColumn($table, $clientCol)) {
            return;
        }

        $hasMatter = Schema::hasColumn($table, 'client_matter_id');
        $rows = DB::table($table)->where($clientCol, $fromId)->get();

        foreach ($rows as $row) {
            $query = DB::table($table)->where($clientCol, $toId)->where('id', '!=', $row->id);
            if ($hasMatter) {
                if (! empty($row->client_matter_id)) {
                    $query->where('client_matter_id', $row->client_matter_id);
                } else {
                    $query->whereNull('client_matter_id');
                }
            }

            if ($query->exists()) {
                DB::table($table)->where('id', $row->id)->delete();
            } else {
                DB::table($table)->where('id', $row->id)->update([$clientCol => $toId]);
            }
        }
    }

    /**
     * Unique (lead_id, matter_id) — drop source row when survivor already has the pair.
     */
    protected function remapUniqueLeadMatterRows(string $table, string $leadCol, ?string $matterCol, int $fromId, int $toId): void
    {
        if (! Schema::hasColumn($table, $leadCol)) {
            return;
        }

        $rows = DB::table($table)->where($leadCol, $fromId)->get();
        foreach ($rows as $row) {
            $query = DB::table($table)->where($leadCol, $toId)->where('id', '!=', $row->id);
            if ($matterCol && Schema::hasColumn($table, $matterCol)) {
                $matterVal = $row->{$matterCol} ?? null;
                if (! empty($matterVal)) {
                    $query->where($matterCol, $matterVal);
                } else {
                    $query->whereNull($matterCol);
                }
            }

            if ($query->exists()) {
                DB::table($table)->where('id', $row->id)->delete();
            } else {
                DB::table($table)->where('id', $row->id)->update([$leadCol => $toId]);
            }
        }
    }
}
