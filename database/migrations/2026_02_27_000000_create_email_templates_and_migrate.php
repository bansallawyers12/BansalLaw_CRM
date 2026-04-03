<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Unifies crm_email_templates, matter_email_templates, matter_other_email_templates
     * into email_templates with nullable matter_id and type discriminator.
     */
    public function up(): void
    {
        DB::transaction(function () {
            Schema::create('email_templates', function (Blueprint $table) {
                $table->id();
                $table->string('type', 50)->index();
                $table->unsignedBigInteger('matter_id')->nullable()->index();
                $table->string('name');
                $table->string('subject')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->foreign('matter_id')->references('id')->on('matters')->onDelete('cascade');
                $table->index(['type', 'matter_id']);
            });

            // PostgreSQL partial unique index: one matter_first per matter
            DB::statement("CREATE UNIQUE INDEX email_templates_matter_first_unique ON email_templates (matter_id) WHERE type = 'matter_first'");

            $mappings = [
                'crm' => ['table' => 'crm_email_templates', 'matter_id' => null],
                'matter_first' => ['table' => 'matter_email_templates', 'matter_id' => 'matter_id'],
                'matter_other' => ['table' => 'matter_other_email_templates', 'matter_id' => 'matter_id'],
            ];

            foreach ($mappings as $type => $config) {
                if (!Schema::hasTable($config['table'])) {
                    continue;
                }
                $rows = DB::table($config['table'])->get();
                foreach ($rows as $row) {
                    DB::table('email_templates')->insert([
                        'type' => $type,
                        'matter_id' => $config['matter_id'] ? ($row->{$config['matter_id']} ?? null) : null,
                        'name' => $row->name ?? '',
                        'subject' => $row->subject ?? null,
                        'description' => $row->description ?? null,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);
                }
                Schema::dropIfExists($config['table']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $mappings = [
            'crm' => 'crm_email_templates',
            'matter_first' => 'matter_email_templates',
            'matter_other' => 'matter_other_email_templates',
        ];

        // Recreate original tables
        Schema::create('crm_email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('matter_email_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('matter_id')->index();
            $table->string('name')->nullable();
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->foreign('matter_id')->references('id')->on('matters')->onDelete('cascade');
        });

        Schema::create('matter_other_email_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('matter_id')->index();
            $table->string('name')->nullable();
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->foreign('matter_id')->references('id')->on('matters')->onDelete('cascade');
        });

        // Copy data back
        foreach ($mappings as $type => $tableName) {
            $rows = DB::table('email_templates')->where('type', $type)->get();
            foreach ($rows as $row) {
                $insert = [
                    'name' => $row->name,
                    'subject' => $row->subject,
                    'description' => $row->description,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
                if ($type !== 'crm') {
                    $insert['matter_id'] = $row->matter_id;
                }
                DB::table($tableName)->insert($insert);
            }
        }

        Schema::dropIfExists('email_templates');
    }
};
