<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop unused client profile child tables and related admins columns.
 * Keeps client_spouse_details, client_addresses, client_contacts, client_emails.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'client_passport_informations',
            'client_visa_countries',
            'client_travel_informations',
            'client_qualifications',
            'client_experiences',
            'client_occupations',
            'client_testscore',
            'client_characters',
            'client_relationships',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        if (! Schema::hasTable('admins')) {
            return;
        }

        $adminCols = array_values(array_filter([
            'related_files',
            'naati_test',
            'naati_date',
            'py_test',
            'py_date',
            'australian_study',
            'australian_study_date',
            'specialist_education',
            'specialist_education_date',
            'regional_study',
            'regional_study_date',
            'visa_expiry_verified_at',
            'visa_expiry_verified_by',
        ], fn (string $c) => Schema::hasColumn('admins', $c)));

        if ($adminCols !== []) {
            Schema::table('admins', function (Blueprint $table) use ($adminCols) {
                $table->dropColumn($adminCols);
            });
        }
    }

    public function down(): void
    {
        // Irreversible data drop — recreate empty stubs only if needed for rollback tooling.
        throw new \RuntimeException('Cannot reverse drop of client profile child tables.');
    }
};
