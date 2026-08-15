<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop unused skilled-migration Age/English points cache.
     * The table is already absent from current Postgres; this is a no-op there.
     */
    public function up(): void
    {
        Schema::dropIfExists('client_points');
    }

    public function down(): void
    {
        // Intentionally empty: this feature is retired and should not be recreated.
    }
};
