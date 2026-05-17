<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Split trust_practice_sequences by entry type so that trust receipts (TR-)
 * and trust journal entries (TJ-) maintain independent sequential numbering.
 *
 * A gap in TR-* would raise questions during an external examination;
 * journals must not consume receipt numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('trust_practice_sequences', 'sequence_type')) {
            Schema::table('trust_practice_sequences', function (Blueprint $table) {
                // Add before modifying constraints
                $table->string('sequence_type', 8)->default('TR')->after('id')
                    ->comment('TR = trust receipt; TJ = trust journal');
            });

            // Migrate existing rows to type TR (they were all receipts)
            DB::table('trust_practice_sequences')->update(['sequence_type' => 'TR']);

            Schema::table('trust_practice_sequences', function (Blueprint $table) {
                // Drop the old unique index on year alone
                $table->dropUnique(['trust_year_start_year']);
                // New composite unique: one counter per (year, type)
                $table->unique(['trust_year_start_year', 'sequence_type'], 'trust_seq_year_type_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('trust_practice_sequences', 'sequence_type')) {
            Schema::table('trust_practice_sequences', function (Blueprint $table) {
                $table->dropUnique('trust_seq_year_type_unique');
                $table->dropColumn('sequence_type');
                $table->unique('trust_year_start_year');
            });
        }
    }
};
