<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * No-op: is_pinned for client_eoi_references is now added by
     * 2026_02_16_100000_add_is_pinned_to_client_reference_tables.
     */
    public function up(): void
    {
        // Consolidated into add_is_pinned_to_client_reference_tables
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
