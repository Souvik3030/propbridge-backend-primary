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
        Schema::create('dld_developers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('license_number')->unique();
            $table->date('registration_date')->index();
            $table->date('expiry_date');
            $table->string('phone_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dld_developers');
    }
};
