<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dld_transactions', function (Blueprint $table) {
            $table->index('instance_date');
            $table->index('is_offplan_en');
            $table->index('area_en');
            $table->index('rooms_en');
            $table->index('trans_value');
            $table->index('group_en');
        });
    }

    public function down(): void
    {
        Schema::table('dld_transactions', function (Blueprint $table) {
            $table->dropIndex(['instance_date']);
            $table->dropIndex(['is_offplan_en']);
            $table->dropIndex(['area_en']);
            $table->dropIndex(['rooms_en']);
            $table->dropIndex(['trans_value']);
            $table->dropIndex(['group_en']);
        });
    }
};
