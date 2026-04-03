<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops 8 unused columns from client_art_references (workflow/confirmation
     * columns mirrored from EOI but never implemented for ART).
     */
    public function up(): void
    {
        Schema::table('client_art_references', function (Blueprint $table) {
            // Drop foreign key before dropping verified_by
            $table->dropForeign(['verified_by']);
        });

        $columnsToDrop = [
            'other_reference',
            'staff_verified',
            'verification_date',
            'verified_by',
            'client_confirmation_status',
            'client_last_confirmation',
            'client_confirmation_notes',
            'client_confirmation_token',
            'confirmation_email_sent_at',
        ];

        foreach ($columnsToDrop as $column) {
            if (Schema::hasColumn('client_art_references', $column)) {
                Schema::table('client_art_references', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_art_references', function (Blueprint $table) {
            if (!Schema::hasColumn('client_art_references', 'other_reference')) {
                $table->string('other_reference')->nullable()->after('client_matter_id');
            }
            if (!Schema::hasColumn('client_art_references', 'staff_verified')) {
                $table->boolean('staff_verified')->default(false)->after('comments');
            }
            if (!Schema::hasColumn('client_art_references', 'verification_date')) {
                $table->timestamp('verification_date')->nullable()->after('staff_verified');
            }
            if (!Schema::hasColumn('client_art_references', 'verified_by')) {
                $table->unsignedBigInteger('verified_by')->nullable()->after('verification_date');
            }
            if (!Schema::hasColumn('client_art_references', 'client_confirmation_status')) {
                $table->string('client_confirmation_status', 50)->nullable()->after('verified_by');
            }
            if (!Schema::hasColumn('client_art_references', 'client_last_confirmation')) {
                $table->timestamp('client_last_confirmation')->nullable()->after('client_confirmation_status');
            }
            if (!Schema::hasColumn('client_art_references', 'client_confirmation_notes')) {
                $table->text('client_confirmation_notes')->nullable()->after('client_last_confirmation');
            }
            if (!Schema::hasColumn('client_art_references', 'client_confirmation_token')) {
                $table->string('client_confirmation_token')->nullable()->after('client_confirmation_notes');
            }
            if (!Schema::hasColumn('client_art_references', 'confirmation_email_sent_at')) {
                $table->timestamp('confirmation_email_sent_at')->nullable()->after('client_confirmation_token');
            }
        });

        Schema::table('client_art_references', function (Blueprint $table) {
            $table->foreign('verified_by')->references('id')->on('admins')->onDelete('set null');
        });
    }
};
