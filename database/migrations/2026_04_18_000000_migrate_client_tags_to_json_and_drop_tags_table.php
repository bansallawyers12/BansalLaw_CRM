<?php

use App\Support\ClientTagStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move tag references from the global tags table onto admins.tagname as JSON, then drop tags.
     */
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        if (! Schema::hasColumn('admins', 'tagname')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->text('tagname')->nullable();
            });
        }

        DB::table('admins')
            ->whereNotNull('tagname')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $raw = trim((string) ($row->tagname ?? ''));
                    if ($raw === '') {
                        continue;
                    }
                    if (str_starts_with($raw, '{')) {
                        $j = json_decode($raw, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($j)
                            && (array_key_exists('n', $j) || array_key_exists('r', $j))) {
                            continue;
                        }
                    }
                    [$n, $r] = ClientTagStorage::decode($raw);
                    DB::table('admins')->where('id', $row->id)->update([
                        'tagname' => ClientTagStorage::encode($n, $r),
                    ]);
                }
            });

        Schema::dropIfExists('tags');
    }

    public function down(): void
    {
        if (! Schema::hasTable('tags')) {
            Schema::create('tags', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('tag_type', 20)->default('normal');
                $table->boolean('is_hidden')->default(false);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }
};
