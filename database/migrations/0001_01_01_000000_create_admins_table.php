<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('admins')) {
            return;
        }
        
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->integer('role')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('decrypt_password')->nullable();
            $table->integer('country')->nullable();
            $table->integer('state')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('zip')->nullable();
            $table->string('profile_img')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->string('service_token')->nullable();
            $table->timestamp('token_generated_at')->nullable();
            $table->tinyInteger('cp_status')->default(0);
            $table->string('cp_random_code')->nullable();
            $table->tinyInteger('cp_code_verify')->default(0);
            $table->timestamp('cp_token_generated_at')->nullable();
            $table->timestamp('visa_expiry_verified_at')->nullable();
            $table->integer('visa_expiry_verified_by')->nullable();
            $table->string('naati_test')->nullable();
            $table->string('py_test')->nullable();
            $table->date('naati_date')->nullable();
            $table->date('py_date')->nullable();
            $table->string('martial_status')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};

