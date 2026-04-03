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
        // Create message_recipients pivot table only if it doesn't exist
        if (!Schema::hasTable('message_recipients')) {
            Schema::create('message_recipients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('message_id');
                $table->unsignedBigInteger('recipient_id');
                $table->string('recipient')->nullable(); // Recipient name
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                // Foreign key constraint
                $table->foreign('message_id')
                      ->references('id')
                      ->on('messages')
                      ->onDelete('cascade');

                // Indexes for better query performance
                $table->index('message_id');
                $table->index('recipient_id');
                $table->index(['recipient_id', 'is_read']); // For unread count queries
                $table->index(['message_id', 'recipient_id']); // For lookup queries
            });
        }

        // Remove recipient-related columns from messages table
        Schema::table('messages', function (Blueprint $table) {
            // Drop columns if they exist
            if (Schema::hasColumn('messages', 'recipient_id')) {
                $table->dropColumn('recipient_id');
            }
            if (Schema::hasColumn('messages', 'recipient')) {
                $table->dropColumn('recipient');
            }
            if (Schema::hasColumn('messages', 'is_read')) {
                $table->dropColumn('is_read');
            }
            if (Schema::hasColumn('messages', 'read_at')) {
                $table->dropColumn('read_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back recipient columns to messages table
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('recipient_id')->nullable()->after('sender_id');
            $table->string('recipient')->nullable()->after('sender');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
        });

        // Drop message_recipients table
        Schema::dropIfExists('message_recipients');
    }
};

