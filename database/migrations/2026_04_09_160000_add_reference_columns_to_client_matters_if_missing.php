<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Previously added client_matters.department_reference and other_reference.
     * That feature was removed; columns are dropped by
     * 2026_04_16_120000_drop_department_and_other_reference_from_client_matters.
     *
     * This file is a no-op so migration batch history stays valid if this migration
     * already ran before the feature was retired, and rollback does not fail on a missing file.
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
