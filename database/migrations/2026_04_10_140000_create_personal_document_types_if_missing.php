<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL / minimal installs may lack personal_document_types (legacy MySQL had no migration in repo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personal_document_types')) {
            return;
        }

        Schema::create('personal_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->tinyInteger('status')->default(1);
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();
        });

        DB::table('personal_document_types')->insert([
            'id' => 1,
            'title' => 'General',
            'status' => 1,
            'client_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('personal_document_types', 'id'), COALESCE((SELECT MAX(id) FROM personal_document_types), 1))");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('personal_document_types')) {
            return;
        }

        Schema::drop('personal_document_types');
    }
};
