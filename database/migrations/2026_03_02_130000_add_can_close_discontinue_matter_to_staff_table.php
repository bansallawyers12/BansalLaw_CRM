<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'can_close_discontinue_matter')) {
                $table->boolean('can_close_discontinue_matter')
                    ->default(false)
                    ->after('can_delete_email_with_attachments');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'can_close_discontinue_matter')) {
                $table->dropColumn('can_close_discontinue_matter');
            }
        });
    }
};
