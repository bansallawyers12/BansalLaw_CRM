<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * EOI/ROI workflow columns for client_eoi_references were removed from the migration path.
     * Kept as a no-op so existing migration history stays aligned.
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
