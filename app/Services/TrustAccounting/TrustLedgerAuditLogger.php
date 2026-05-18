<?php

namespace App\Services\TrustAccounting;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

class TrustLedgerAuditLogger
{
    /** Cached result of Schema::hasTable to avoid repeated information_schema queries. */
    private static ?bool $tableExists = null;

    private static function auditTableExists(): bool
    {
        if (self::$tableExists === null) {
            self::$tableExists = Schema::hasTable('trust_audit_logs');
        }

        return self::$tableExists;
    }

    public static function log(
        string $event,
        int $rowId,
        ?string $fieldName = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?string $context = null
    ): void {
        self::logForTable('account_client_receipts', $event, $rowId, $fieldName, $oldValue, $newValue, $context);
    }

    /**
     * Append-only audit row for any trust-related table (ledger rows, period locks, etc.).
     */
    public static function logForTable(
        string $tableName,
        string $event,
        int $rowId,
        ?string $fieldName = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?string $context = null
    ): void {
        if (! self::auditTableExists()) {
            return;
        }

        $uid = null;
        if (Auth::guard('admin')->check()) {
            $uid = Auth::guard('admin')->id();
        } elseif (Auth::check()) {
            $uid = Auth::id();
        }

        DB::table('trust_audit_logs')->insert([
            'table_name' => $tableName,
            'row_id' => $rowId,
            'event' => $event,
            'field_name' => $fieldName,
            'old_value' => $oldValue !== null ? (is_scalar($oldValue) ? (string) $oldValue : json_encode($oldValue)) : null,
            'new_value' => $newValue !== null ? (is_scalar($newValue) ? (string) $newValue : json_encode($newValue)) : null,
            'performed_by' => $uid,
            'ip_address' => Request::ip(),
            'context' => $context,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
