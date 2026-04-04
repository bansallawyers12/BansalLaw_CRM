<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * trans_date / entry_date must stay as VARCHAR dd/mm/yyyy for PostgreSQL
     * TO_DATE(trans_date, 'DD/MM/YYYY') used in ClientAccountsController & FinancialStatsService.
     *
     * The initial core-columns migration used date() by mistake; convert existing DATE columns only.
     */
    public function up(): void
    {
        if (! Schema::hasTable('account_client_receipts')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'pgsql') {
            return;
        }

        foreach (['trans_date', 'entry_date'] as $column) {
            if (! Schema::hasColumn('account_client_receipts', $column)) {
                continue;
            }

            $row = DB::selectOne(
                'select data_type from information_schema.columns
                 where table_schema = current_schema()
                   and table_name = ?
                   and column_name = ?',
                ['account_client_receipts', $column]
            );

            if (! $row || ($row->data_type ?? '') !== 'date') {
                continue;
            }

            DB::statement(
                "alter table account_client_receipts alter column {$column} type varchar(32) using (
                    case when {$column} is null then null else to_char({$column}, 'DD/MM/YYYY') end
                )"
            );
        }
    }

    /**
     * Reverting would require parsing dd/mm/yyyy back to date; not supported.
     */
    public function down(): void
    {
    }
};
