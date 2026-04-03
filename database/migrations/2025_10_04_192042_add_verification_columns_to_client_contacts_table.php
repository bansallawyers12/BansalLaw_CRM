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
        Schema::table('client_contacts', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false)->after('phone');
            $table->timestamp('verified_at')->nullable()->after('is_verified');
            $table->unsignedInteger('verified_by')->nullable()->after('verified_at');
            
            $table->foreign('verified_by')
                  ->references('id')
                  ->on('admins')
                  ->onDelete('set null');
            
            $table->index('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_contacts', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['is_verified', 'verified_at', 'verified_by']);
        });
    }
};