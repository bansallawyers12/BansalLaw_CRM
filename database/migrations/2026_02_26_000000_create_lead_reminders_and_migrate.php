<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unused consolidation table — never wired to application code
     * (dropped in 2026_06_23_130000_drop_unused_tables).
     */
    public function up(): void
    {
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_reminders');
    }
};
