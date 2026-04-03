<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_eoi_references', function (Blueprint $table) {
            // Staff verification
            $table->boolean('staff_verified')->default(false)->after('eoi_status')
                ->comment('Whether staff has verified the EOI details');
            $table->timestamp('confirmation_date')->nullable()->after('staff_verified')
                ->comment('Date when staff verified the EOI details');
            $table->unsignedBigInteger('checked_by')->nullable()->after('confirmation_date')
                ->comment('Admin user who verified the EOI details');
            
            // Client confirmation
            $table->enum('client_confirmation_status', ['pending', 'confirmed', 'amendment_requested'])
                ->default('pending')->after('checked_by')
                ->comment('Status of client confirmation');
            $table->timestamp('client_last_confirmation')->nullable()->after('client_confirmation_status')
                ->comment('Date when client last confirmed the EOI details');
            $table->text('client_confirmation_notes')->nullable()->after('client_last_confirmation')
                ->comment('Notes from client if requesting amendments');
            $table->string('client_confirmation_token', 64)->nullable()->unique()->after('client_confirmation_notes')
                ->comment('Unique token for client email confirmation');
            $table->timestamp('confirmation_email_sent_at')->nullable()->after('client_confirmation_token')
                ->comment('When the confirmation email was sent to client');
            
            // Add foreign key for checked_by
            $table->foreign('checked_by')->references('id')->on('admins')->onDelete('set null');
            
            // Add indexes
            $table->index('staff_verified');
            $table->index('client_confirmation_status');
            $table->index('client_confirmation_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_eoi_references', function (Blueprint $table) {
            // Drop foreign key
            $table->dropForeign(['checked_by']);
            
            // Drop indexes
            $table->dropIndex(['staff_verified']);
            $table->dropIndex(['client_confirmation_status']);
            $table->dropIndex(['client_confirmation_token']);
            
            // Drop columns
            $table->dropColumn([
                'staff_verified',
                'confirmation_date',
                'checked_by',
                'client_confirmation_status',
                'client_last_confirmation',
                'client_confirmation_notes',
                'client_confirmation_token',
                'confirmation_email_sent_at'
            ]);
        });
    }
};
