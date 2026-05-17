<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 (Rule 42): withdrawal authority metadata for each Fee Transfer ledger row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff') || ! Schema::hasTable('account_client_receipts')) {
            return;
        }

        Schema::create('trust_withdrawal_authority_types', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('trust_withdrawal_authorities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_client_receipt_id');
            $table->unsignedBigInteger('client_id');
            $table->string('invoice_no', 191)->nullable();
            $table->decimal('withdrawal_amount', 14, 2);
            $table->foreignId('authority_type_id')->constrained('trust_withdrawal_authority_types')->restrictOnDelete();
            $table->unsignedBigInteger('authorised_by_staff_id');
            $table->date('notice_given_date')->nullable();
            $table->text('authority_notes')->nullable();
            $table->boolean('supervisor_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->timestamps();

            $table->unique(['account_client_receipt_id']);
            $table->index(['client_id', 'invoice_no']);

            $table->foreign('account_client_receipt_id')
                ->references('id')
                ->on('account_client_receipts')
                ->cascadeOnDelete();

            $table->foreign('authorised_by_staff_id')
                ->references('id')
                ->on('staff')
                ->restrictOnDelete();
        });

        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'trust_rule42_supervisor')) {
                $table->boolean('trust_rule42_supervisor')->default(false);
            }
        });

        $now = now();
        $defaults = [
            ['label' => 'Bill served — 7 business days / no objection (Rule 42(3))', 'sort_order' => 10],
            ['label' => 'Written client authority / instructions (Rule 42(4))', 'sort_order' => 20],
            ['label' => 'Reimbursement / disbursement recovery (Rule 42(5))', 'sort_order' => 30],
            ['label' => 'Commercial or government client — costs agreement (Rule 42(6))', 'sort_order' => 40],
            ['label' => 'Other — describe in notes', 'sort_order' => 90],
        ];
        foreach ($defaults as $row) {
            DB::table('trust_withdrawal_authority_types')->insert([
                'label' => $row['label'],
                'sort_order' => $row['sort_order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('staff') && Schema::hasColumn('staff', 'trust_rule42_supervisor')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropColumn('trust_rule42_supervisor');
            });
        }

        if (Schema::hasTable('trust_withdrawal_authorities')) {
            Schema::dropIfExists('trust_withdrawal_authorities');
        }

        if (Schema::hasTable('trust_withdrawal_authority_types')) {
            Schema::dropIfExists('trust_withdrawal_authority_types');
        }
    }
};
