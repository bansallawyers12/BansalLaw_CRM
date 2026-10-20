<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent repair when a local-only migration removed branches/office_id columns.
 * No-op on production when schema is already correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->string('office_name')->default('');
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('zip', 32)->nullable();
                $table->string('country')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('mobile', 64)->nullable();
                $table->string('contact_person')->nullable();
                $table->string('choose_admin')->nullable();
                $table->timestamps();
            });
        }

        $this->ensureOfficeIdOnTable('staff');
        $this->ensureClientMattersOfficeId();
        $this->ensureOfficeIdOnTable('documents', ['idx_documents_office']);
        $this->ensureOfficeIdOnTable('client_access_grants');

        $this->seedDefaultBranches();
    }

    public function down(): void
    {
        //
    }

    private function ensureClientMattersOfficeId(): void
    {
        if (! Schema::hasTable('client_matters') || Schema::hasColumn('client_matters', 'office_id')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            $table->unsignedInteger('office_id')->nullable();
            $table->index('office_id', 'idx_matters_office');
            if (Schema::hasColumn('client_matters', 'matter_status')) {
                $table->index(['office_id', 'matter_status'], 'idx_matters_office_status');
            }
        });

        if (Schema::hasTable('branches')) {
            try {
                Schema::table('client_matters', function (Blueprint $table) {
                    $table->foreign('office_id')->references('id')->on('branches')->nullOnDelete();
                });
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @param  list<string>  $indexNames
     */
    private function ensureOfficeIdOnTable(string $table, array $indexNames = []): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'office_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexNames) {
            $blueprint->unsignedBigInteger('office_id')->nullable();
            foreach ($indexNames as $indexName) {
                $blueprint->index('office_id', $indexName);
            }
        });

        if (Schema::hasTable('branches')) {
            try {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreign('office_id')->references('id')->on('branches')->nullOnDelete();
                });
            } catch (\Throwable) {
                // FK may already exist or driver limitations on partial restore.
            }
        }
    }

    private function seedDefaultBranches(): void
    {
        if (! Schema::hasTable('branches') || ! Schema::hasColumn('branches', 'office_name')) {
            return;
        }

        $now = now();
        foreach (
            [
                ['office_name' => 'Melbourne', 'city' => 'Melbourne', 'state' => 'VIC', 'country' => 'Australia'],
                ['office_name' => 'India', 'city' => null, 'state' => null, 'country' => 'India'],
            ] as $row
        ) {
            if (DB::table('branches')->whereRaw('LOWER(TRIM(office_name)) = ?', [strtolower($row['office_name'])])->exists()) {
                continue;
            }
            DB::table('branches')->insert(array_merge($row, ['created_at' => $now, 'updated_at' => $now]));
        }
    }
};
