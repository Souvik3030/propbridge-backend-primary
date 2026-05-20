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
            $indexes = Schema::getIndexes('dld_transactions');
            $indexNames = array_column($indexes, 'name');
            if (!in_array('dld_transactions_area_en_index', $indexNames)) {
                $table->index('area_en');
            }
            if (!in_array('dld_transactions_instance_date_index', $indexNames)) {
                $table->index('instance_date');
            }
            if (!in_array('dld_transactions_group_en_index', $indexNames)) {
                $table->index('group_en');
            }
            if (!in_array('dld_transactions_is_offplan_en_index', $indexNames)) {
                $table->index('is_offplan_en');
            }
            if (!in_array('dld_transactions_rooms_en_index', $indexNames)) {
                $table->index('rooms_en');
            }
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dld_transactions', function (Blueprint $table) {
            $indexes = Schema::getIndexes('dld_transactions');
            $indexNames = array_column($indexes, 'name');
            if (in_array('dld_transactions_area_en_index', $indexNames)) {
                $table->dropIndex(['area_en']);
            }
            if (in_array('dld_transactions_instance_date_index', $indexNames)) {
                $table->dropIndex(['instance_date']);
            }
            if (in_array('dld_transactions_group_en_index', $indexNames)) {
                $table->dropIndex(['group_en']);
            }
            if (in_array('dld_transactions_is_offplan_en_index', $indexNames)) {
                $table->dropIndex(['is_offplan_en']);
            }
            if (in_array('dld_transactions_rooms_en_index', $indexNames)) {
                $table->dropIndex(['rooms_en']);
            }
        });
    }
};
