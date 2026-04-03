<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates companies table to store company-specific data for company leads/clients
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->unique()
                ->comment('Reference to admins.id - one-to-one relationship with company lead/client');
            $table->string('company_name', 255)
                ->comment('Company name');
            $table->string('trading_name', 255)->nullable()
                ->comment('Trading name if different from company name');
            $table->string('ABN_number', 20)->nullable()
                ->comment('Australian Business Number (11 digits)');
            $table->string('ACN', 20)->nullable()
                ->comment('Australian Company Number (9 digits)');
            $table->string('company_type', 50)->nullable()
                ->comment('Business type: Sole Trader, Partnership, Proprietary Company, etc.');
            $table->string('company_website', 255)->nullable()
                ->comment('Company website URL');
            $table->unsignedBigInteger('contact_person_id')->nullable()
                ->comment('Reference to admins.id of the primary contact person');
            $table->string('contact_person_position', 255)->nullable()
                ->comment('Position/Title of primary contact person (e.g., HR Manager, Director)');
            $table->timestamps();
            
            // Indexes (create before foreign keys)
            $table->index('admin_id');
            $table->index('contact_person_id');
            $table->index('company_name');
        });
        
        // For PostgreSQL, ensure primary key exists on admins.id before creating foreign keys
        if (DB::getDriverName() === 'pgsql') {
            // Check if admins.id has a primary key constraint
            $hasPrimaryKey = DB::selectOne("
                SELECT COUNT(*) as count
                FROM pg_constraint 
                WHERE conrelid = 'admins'::regclass 
                AND contype = 'p'
            ");
            
            // If no primary key, create one
            if ($hasPrimaryKey->count == 0) {
                DB::statement('ALTER TABLE admins ADD PRIMARY KEY (id)');
            }
            
            // Now create foreign keys using raw SQL for better PostgreSQL compatibility
            DB::statement('ALTER TABLE companies ADD CONSTRAINT companies_admin_id_foreign FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE companies ADD CONSTRAINT companies_contact_person_id_foreign FOREIGN KEY (contact_person_id) REFERENCES admins(id) ON DELETE SET NULL');
            
            // Add partial index for better query performance
            DB::statement('CREATE INDEX IF NOT EXISTS idx_companies_contact_person_id ON companies(contact_person_id) WHERE contact_person_id IS NOT NULL');
        } else {
            // For MySQL/MariaDB, use standard Laravel foreign key syntax
            Schema::table('companies', function (Blueprint $table) {
                $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
                $table->foreign('contact_person_id')->references('id')->on('admins')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
