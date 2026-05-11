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
        Schema::create('offplan_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('country')->index();
            $table->string('city')->index();
            $table->string('community')->nullable();
            $table->string('sub_community')->nullable();
            $table->string('cluster')->nullable();

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->fullText(['city', 'community'], 'locations_fulltext_index');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offplan_locations');
    }
};
