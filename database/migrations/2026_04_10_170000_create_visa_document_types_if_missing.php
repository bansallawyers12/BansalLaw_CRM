<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL / minimal installs may lack visa_document_types (legacy MySQL had no migration in repo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visa_document_types')) {
            return;
        }

        Schema::create('visa_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->tinyInteger('status')->default(1);
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('client_matter_id')->nullable()->index();
            $table->timestamps();
        });

        DB::table('visa_document_types')->insert([
            'id' => 1,
            'title' => 'General',
            'status' => 1,
            'client_id' => null,
            'client_matter_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('visa_document_types', 'id'), COALESCE((SELECT MAX(id) FROM visa_document_types), 1))");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('visa_document_types')) {
            return;
        }

        Schema::drop('visa_document_types');
    }
};
