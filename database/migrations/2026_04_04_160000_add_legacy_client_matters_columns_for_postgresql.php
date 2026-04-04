<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL / stub installs may have a minimal client_matters row (no sel_matter_id, etc.).
 * Matter list and the rest of the CRM expect the legacy MySQL column set.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            if (! Schema::hasColumn('client_matters', 'sel_matter_id')) {
                $table->unsignedBigInteger('sel_matter_id')->nullable()->index();
            }
            if (! Schema::hasColumn('client_matters', 'workflow_stage_id')) {
                $table->unsignedBigInteger('workflow_stage_id')->nullable()->index();
            }
            if (! Schema::hasColumn('client_matters', 'sel_migration_agent')) {
                $table->unsignedBigInteger('sel_migration_agent')->nullable()->index();
            }
            if (! Schema::hasColumn('client_matters', 'sel_person_responsible')) {
                $table->unsignedBigInteger('sel_person_responsible')->nullable()->index();
            }
            if (! Schema::hasColumn('client_matters', 'sel_person_assisting')) {
                $table->unsignedBigInteger('sel_person_assisting')->nullable()->index();
            }
            if (! Schema::hasColumn('client_matters', 'client_unique_matter_no')) {
                $table->string('client_unique_matter_no', 191)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }

        $cols = [
            'sel_matter_id',
            'workflow_stage_id',
            'sel_migration_agent',
            'sel_person_responsible',
            'sel_person_assisting',
            'client_unique_matter_no',
        ];
        $toDrop = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('client_matters', $c)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
