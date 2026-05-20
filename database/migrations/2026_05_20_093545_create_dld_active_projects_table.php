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
        Schema::create('dld_active_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_name')->index();
            $table->foreignId('developer_id')->constrained('dld_developers')->cascadeOnDelete();
            $table->string('area_name')->index();
            $table->integer('units_count')->nullable();
            $table->decimal('completion_percentage', 5, 2);
            $table->date('estimated_end_date');
            $table->string('escrow_status')->default('VERIFIED');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dld_active_projects');
    }
};
