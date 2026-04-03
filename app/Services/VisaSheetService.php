<?php

namespace App\Services;

use App\Models\Matter;
use App\Models\ClientMatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helper for visa-type sheet operations (checklist recording for clients and leads).
 */
class VisaSheetService
{
    /**
     * Get visa sheet type (tr, visitor, student, pr, employer-sponsored) for a matter by ID.
     */
    public static function getSheetTypeForMatter(int $matterId): ?string
    {
        $matter = Matter::find($matterId);
        if (!$matter) {
            return null;
        }
        $nick = strtolower(trim($matter->nick_name ?? ''));
        $title = strtolower(trim($matter->title ?? ''));
        $visaTypes = config('sheets.visa_types', []);
        foreach ($visaTypes as $sheetType => $config) {
            $nickNames = $config['matter_nick_names'] ?? [];
            $patterns = $config['matter_title_patterns'] ?? [];
            foreach ($nickNames as $n) {
                if ($nick === strtolower(trim((string) $n))) {
                    return $sheetType;
                }
            }
            foreach ($patterns as $p) {
                if (str_contains($title, strtolower(trim((string) $p)))) {
                    return $sheetType;
                }
            }
        }
        return null;
    }

    /**
     * Get the Checklist workflow stage ID by name.
     */
    public static function getChecklistStageId(): ?int
    {
        return DB::table('workflow_stages')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['checklist'])
            ->value('id');
    }

    /**
     * Record checklist sent for a lead. Returns true if recorded.
     */
    public static function recordLeadChecklistSent(int $leadId, int $matterId, ?int $staffId = null): bool
    {
        $sheetType = self::getSheetTypeForMatter($matterId);
        if (!$sheetType) {
            return false;
        }
        $config = config("sheets.visa_types.{$sheetType}", []);
        $refTable = $config['lead_reference_table'] ?? null;
        $remindersTable = $config['lead_reminders_table'] ?? null;
        $refType = $config['reference_type'] ?? $sheetType;
        if (!$refTable || !Schema::hasTable($refTable)) {
            return false;
        }
        $now = now();
        $exists = DB::table($refTable)
            ->where('type', $refType)
            ->where('lead_id', $leadId)
            ->where('matter_id', $matterId)
            ->exists();
        if ($exists) {
            DB::table($refTable)
                ->where('type', $refType)
                ->where('lead_id', $leadId)
                ->where('matter_id', $matterId)
                ->update([
                    'checklist_sent_at' => $now,
                    'updated_by' => $staffId,
                    'updated_at' => $now,
                ]);
        } else {
            DB::table($refTable)->insert([
                'type' => $refType,
                'lead_id' => $leadId,
                'matter_id' => $matterId,
                'checklist_sent_at' => $now,
                'created_by' => $staffId,
                'updated_by' => $staffId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if ($remindersTable && Schema::hasTable($remindersTable)) {
            DB::table($remindersTable)->insert([
                'visa_type' => $refType,
                'lead_id' => $leadId,
                'type' => 'email',
                'reminded_at' => $now,
                'reminded_by' => $staffId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        return true;
    }
}
