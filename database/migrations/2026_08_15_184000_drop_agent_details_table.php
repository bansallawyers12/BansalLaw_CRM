<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * External agent_details directory replaced by staff solicitors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('agent_details');
    }

    public function down(): void
    {
        throw new \RuntimeException('Cannot reverse drop of agent_details.');
    }
};
