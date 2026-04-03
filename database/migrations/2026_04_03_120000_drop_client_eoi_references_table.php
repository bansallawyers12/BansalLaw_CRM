<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('client_eoi_references');
    }

    public function down(): void
    {
        // Intentionally empty: EOI/ROI feature removed; table is not recreated.
    }
};
