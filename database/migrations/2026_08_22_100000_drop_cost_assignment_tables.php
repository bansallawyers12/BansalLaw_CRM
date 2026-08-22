<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retire per-matter cost assignment forms (replaced by matters-table defaults + Legal Forms).
     */
    public function up(): void
    {
        Schema::dropIfExists('disbursement_lines');
        Schema::dropIfExists('cost_assignment_forms');
    }

    public function down(): void
    {
        // Tables recreated by 2026_04_11_100000 and 2026_04_12_130000 if rollback is required.
    }
};
