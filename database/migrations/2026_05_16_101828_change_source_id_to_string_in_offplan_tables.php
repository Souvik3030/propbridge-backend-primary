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
        Schema::table('offplan_projects', function (Blueprint $table) {
            $table->string('source_id')->change();
        });

        Schema::table('offplan_developers', function (Blueprint $table) {
            $table->string('source_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offplan_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->change();
        });

        Schema::table('offplan_developers', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->change();
        });
    }
};
