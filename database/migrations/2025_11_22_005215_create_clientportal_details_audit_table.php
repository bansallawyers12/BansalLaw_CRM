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
        Schema::create('clientportal_details_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('client_id')->comment('FK to admins.id');
            $table->string('meta_key', 100)->comment('Field name that was changed');
            $table->text('old_value')->nullable()->comment('Previous value');
            $table->text('new_value')->nullable()->comment('New value');
            $table->unsignedInteger('meta_order')->nullable()->comment('Order of the value that changed');
            $table->string('meta_type', 50)->nullable()->comment('Type of the value that changed');
            $table->string('action', 20)->default('update')->comment('create, update, delete');
            $table->unsignedInteger('updated_by')->nullable()->comment('FK to admins.id - who made the change');
            $table->timestamp('updated_at')->nullable()->comment('When the change was made');
            
            // Indexes
            $table->index('client_id', 'idx_audit_client_id');
            $table->index('meta_key', 'idx_audit_meta_key');
            $table->index('updated_at', 'idx_audit_updated_at');
            $table->index(['client_id', 'meta_key'], 'idx_audit_client_meta');
            
            // Foreign keys
            $table->foreign('client_id')->references('id')->on('admins')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('admins')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientportal_details_audit');
    }
};
