<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy installs expected a `countries` table; it was never added to stub migrations.
 * Creates the table (matching App\Models\Country) and seeds from database/data/countries_seed.json when empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->string('sortname', 8)->unique();
                $table->string('name', 255);
                $table->string('phonecode', 32)->nullable();
                $table->unsignedTinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('countries')) {
            return;
        }

        if (DB::table('countries')->exists()) {
            return;
        }

        $path = database_path('data/countries_seed.json');
        if (! is_file($path)) {
            return;
        }

        $rows = json_decode(file_get_contents($path), true);
        if (! is_array($rows) || $rows === []) {
            return;
        }

        foreach (array_chunk($rows, 75) as $chunk) {
            DB::table('countries')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
