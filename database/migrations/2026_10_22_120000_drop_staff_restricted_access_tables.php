<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('staff_access_audit');
        Schema::dropIfExists('staff_record_grants');
        Schema::dropIfExists('staff_access_profiles');
    }

    public function down(): void
    {
        // Staff Access feature removed; restore via older migrations if ever needed.
    }
};
