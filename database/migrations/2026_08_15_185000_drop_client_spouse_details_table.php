<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Unused EOI/spouse profile table — no model references in app code; empty in prod DB.
 * Missed by 2026_08_15_150000_drop_client_profile_child_tables (added after that migration ran).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('client_spouse_details');
    }

    public function down(): void
    {
        throw new \RuntimeException('Cannot reverse drop of client_spouse_details.');
    }
};
