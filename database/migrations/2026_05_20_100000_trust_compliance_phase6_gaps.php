<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trust compliance phase 6: payment detail fields, statement tracking, monthly archives.
     */
    public function up(): void
    {
        if (Schema::hasTable('account_client_receipts')) {
            Schema::table('account_client_receipts', function (Blueprint $table) {
                if (! Schema::hasColumn('account_client_receipts', 'payee_name')) {
                    $table->string('payee_name', 255)->nullable()->after('banking_date');
                }
                if (! Schema::hasColumn('account_client_receipts', 'cheque_number')) {
                    $table->string('cheque_number', 32)->nullable()->after('payee_name');
                }
                if (! Schema::hasColumn('account_client_receipts', 'eft_account_name')) {
                    $table->string('eft_account_name', 255)->nullable()->after('cheque_number');
                }
                if (! Schema::hasColumn('account_client_receipts', 'eft_bsb')) {
                    $table->string('eft_bsb', 16)->nullable()->after('eft_account_name');
                }
                if (! Schema::hasColumn('account_client_receipts', 'eft_account_number')) {
                    $table->string('eft_account_number', 64)->nullable()->after('eft_bsb');
                }
            });
        }

        if (Schema::hasTable('client_matters')) {
            Schema::table('client_matters', function (Blueprint $table) {
                if (! Schema::hasColumn('client_matters', 'trust_last_statement_sent_at')) {
                    $table->timestamp('trust_last_statement_sent_at')->nullable()->after('updated_at');
                }
            });
        }

        if (! Schema::hasTable('trust_monthly_archives')) {
            Schema::create('trust_monthly_archives', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('period_year');
                $table->unsignedTinyInteger('period_month');
                $table->string('archive_type', 32);
                $table->unsignedBigInteger('prepared_by_staff_id')->nullable();
                $table->timestamp('prepared_at')->nullable();
                $table->unsignedBigInteger('file_document_id')->nullable();
                $table->string('status', 16)->default('finalised');
                $table->timestamps();
                $table->unique(['period_year', 'period_month', 'archive_type'], 'trust_archive_period_type_unique');
                $table->index(['period_year', 'period_month']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_monthly_archives');

        if (Schema::hasTable('client_matters') && Schema::hasColumn('client_matters', 'trust_last_statement_sent_at')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->dropColumn('trust_last_statement_sent_at');
            });
        }

        if (Schema::hasTable('account_client_receipts')) {
            Schema::table('account_client_receipts', function (Blueprint $table) {
                foreach (['eft_account_number', 'eft_bsb', 'eft_account_name', 'cheque_number', 'payee_name'] as $col) {
                    if (Schema::hasColumn('account_client_receipts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
