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
        Schema::create('dld_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->nullable();
            $table->dateTime('instance_date')->nullable();
            $table->string('group_en')->nullable();
            $table->string('procedure_en')->nullable();
            $table->string('is_offplan_en')->nullable();
            $table->string('is_free_hold_en')->nullable();
            $table->string('usage_en')->nullable();
            $table->string('area_en')->nullable();
            $table->string('prop_type_en')->nullable();
            $table->string('prop_sb_type_en')->nullable();
            $table->decimal('trans_value', 20, 2)->nullable();
            $table->decimal('procedure_area', 20, 2)->nullable();
            $table->decimal('actual_area', 20, 2)->nullable();
            $table->string('rooms_en')->nullable();
            $table->string('parking')->nullable();
            $table->string('nearest_metro_en')->nullable();
            $table->string('nearest_mall_en')->nullable();
            $table->string('nearest_landmark_en')->nullable();
            $table->integer('total_buyer')->nullable();
            $table->integer('total_seller')->nullable();
            $table->string('master_project_en')->nullable();
            $table->string('project_en')->nullable();
            $table->timestamps();

            $table->index('project_en');
            $table->index('master_project_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dld_transactions');
    }
};
