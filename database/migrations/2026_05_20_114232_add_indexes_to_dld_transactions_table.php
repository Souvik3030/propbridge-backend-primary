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
        Schema::table('dld_transactions', function (Blueprint $table) {
            $table->index('area_en');
            $table->index('instance_date');
            $table->index('group_en');
            $table->index('is_offplan_en');
            $table->index('rooms_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dld_transactions', function (Blueprint $table) {
            $table->dropIndex(['area_en']);
            $table->dropIndex(['instance_date']);
            $table->dropIndex(['group_en']);
            $table->dropIndex(['is_offplan_en']);
            $table->dropIndex(['rooms_en']);
        });
    }
};
