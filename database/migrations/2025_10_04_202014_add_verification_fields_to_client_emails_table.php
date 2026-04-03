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
        Schema::table('client_emails', function (Blueprint $table) {
            // Add email verification fields
            $table->boolean('is_verified')->default(false)->after('email');
            $table->timestamp('verified_at')->nullable()->after('is_verified');
            $table->unsignedInteger('verified_by')->nullable()->after('verified_at');
            $table->string('verification_token', 255)->nullable()->after('verified_by');
            $table->timestamp('token_expires_at')->nullable()->after('verification_token');
            $table->timestamp('verification_sent_at')->nullable()->after('token_expires_at');
            
            // Add foreign key constraint
            $table->foreign('verified_by')->references('id')->on('admins')->onDelete('set null');
            
            // Add indexes for performance
            $table->index(['is_verified', 'verification_token']);
            $table->index('token_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_emails', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['verified_by']);
            
            // Drop the verification fields
            $table->dropColumn([
                'is_verified',
                'verified_at', 
                'verified_by',
                'verification_token',
                'token_expires_at',
                'verification_sent_at'
            ]);
        });
    }
};