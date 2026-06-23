<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop tables that were created by migrations but never wired to application code.
     * All were empty at time of removal.
     */
    public function up(): void
    {
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_recipients');
        Schema::dropIfExists('messages');

        Schema::dropIfExists('document_notes');

        Schema::dropIfExists('client_art_references');
        Schema::dropIfExists('client_matter_references');
        Schema::dropIfExists('lead_matter_references');
        Schema::dropIfExists('lead_reminders');
        Schema::dropIfExists('matter_reminders');
    }

    public function down(): void
    {
        // Intentionally empty — these tables were unused legacy/migration-only artifacts.
    }
};
