<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop unused client-profile columns:
 * - client_passport_informations.passport_number (app uses `passport`)
 * - client_characters.character_date (not saved by modern edit UI)
 * - client_experiences.fte_multiplier (always hardcoded; no UI)
 * - client_spouse_details is_citizen/has_pr/dob (EOI fields never written)
 *
 * Leaves client_spouse_details table itself in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_passport_informations')
            && Schema::hasColumn('client_passport_informations', 'passport_number')) {
            Schema::table('client_passport_informations', function (Blueprint $table) {
                $table->dropColumn('passport_number');
            });
        }

        if (Schema::hasTable('client_characters')
            && Schema::hasColumn('client_characters', 'character_date')) {
            Schema::table('client_characters', function (Blueprint $table) {
                $table->dropColumn('character_date');
            });
        }

        if (Schema::hasTable('client_experiences')
            && Schema::hasColumn('client_experiences', 'fte_multiplier')) {
            Schema::table('client_experiences', function (Blueprint $table) {
                $table->dropColumn('fte_multiplier');
            });
        }

        if (Schema::hasTable('client_spouse_details')) {
            $spouseCols = array_values(array_filter(
                ['is_citizen', 'has_pr', 'dob'],
                fn (string $c) => Schema::hasColumn('client_spouse_details', $c)
            ));
            if ($spouseCols !== []) {
                Schema::table('client_spouse_details', function (Blueprint $table) use ($spouseCols) {
                    $table->dropColumn($spouseCols);
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('client_passport_informations')
            && ! Schema::hasColumn('client_passport_informations', 'passport_number')) {
            Schema::table('client_passport_informations', function (Blueprint $table) {
                $table->string('passport_number')->nullable();
            });
        }

        if (Schema::hasTable('client_characters')
            && ! Schema::hasColumn('client_characters', 'character_date')) {
            Schema::table('client_characters', function (Blueprint $table) {
                $table->date('character_date')->nullable();
            });
        }

        if (Schema::hasTable('client_experiences')
            && ! Schema::hasColumn('client_experiences', 'fte_multiplier')) {
            Schema::table('client_experiences', function (Blueprint $table) {
                $table->decimal('fte_multiplier', 3, 2)->default(1.00);
            });
        }

        if (Schema::hasTable('client_spouse_details')) {
            Schema::table('client_spouse_details', function (Blueprint $table) {
                if (! Schema::hasColumn('client_spouse_details', 'is_citizen')) {
                    $table->boolean('is_citizen')->default(0);
                }
                if (! Schema::hasColumn('client_spouse_details', 'has_pr')) {
                    $table->boolean('has_pr')->default(0);
                }
                if (! Schema::hasColumn('client_spouse_details', 'dob')) {
                    $table->date('dob')->nullable();
                }
            });
        }
    }
};
