<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Practitioner form fields no longer shown or saved. All staff rows were null.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const COLUMNS = [
        'legal_practitioner_number',
        'business_phone',
        'business_mobile',
        'tax_number',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        $drop = array_values(array_filter(
            self::COLUMNS,
            fn (string $column) => Schema::hasColumn('staff', $column)
        ));

        if ($drop === []) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) use ($drop) {
            $table->dropColumn($drop);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'legal_practitioner_number')) {
                $table->string('legal_practitioner_number', 100)->nullable();
            }
            if (! Schema::hasColumn('staff', 'business_phone')) {
                $table->string('business_phone', 100)->nullable();
            }
            if (! Schema::hasColumn('staff', 'business_mobile')) {
                $table->string('business_mobile', 100)->nullable();
            }
            if (! Schema::hasColumn('staff', 'tax_number')) {
                $table->string('tax_number', 100)->nullable();
            }
        });
    }
};
