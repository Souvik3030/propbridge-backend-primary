<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offplan_projects', function (Blueprint $table) {
            // Missing price/area fields
            if (!Schema::hasColumn('offplan_projects', 'price_max')) {
                $table->decimal('price_max', 15, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('offplan_projects', 'area_min')) {
                $table->decimal('area_min', 10, 2)->nullable()->after('price_max');
            }
            if (!Schema::hasColumn('offplan_projects', 'area_max')) {
                $table->decimal('area_max', 10, 2)->nullable()->after('area_min');
            }
            if (!Schema::hasColumn('offplan_projects', 'area_built_up')) {
                $table->decimal('area_built_up', 10, 2)->nullable()->after('area_max');
            }

            // Bedroom configs (all possible rooms as JSON array)
            if (!Schema::hasColumn('offplan_projects', 'rooms')) {
                $table->json('rooms')->nullable()->after('bedrooms');
            }

            // Unit counts
            if (!Schema::hasColumn('offplan_projects', 'units_count')) {
                $table->integer('units_count')->default(0)->after('rooms');
            }

            // Status & completion
            if (!Schema::hasColumn('offplan_projects', 'completion_status')) {
                $table->string('completion_status')->nullable()->after('units_count'); // off_plan, completed, under_construction
            }
            if (!Schema::hasColumn('offplan_projects', 'completion_date')) {
                $table->date('completion_date')->nullable()->after('completion_status');
            }

            // Analytics / scoring
            if (!Schema::hasColumn('offplan_projects', 'investment_score')) {
                $table->integer('investment_score')->nullable()->after('completion_date');
            }
            if (!Schema::hasColumn('offplan_projects', 'estimated_yield')) {
                $table->decimal('estimated_yield', 5, 2)->nullable()->after('investment_score');
            }
            if (!Schema::hasColumn('offplan_projects', 'dld_avg_price_sqft')) {
                $table->decimal('dld_avg_price_sqft', 10, 2)->nullable()->after('estimated_yield');
            }
            if (!Schema::hasColumn('offplan_projects', 'dld_transactions_count')) {
                $table->integer('dld_transactions_count')->nullable()->after('dld_avg_price_sqft');
            }

            // Documents (brochures, floorplans)
            if (!Schema::hasColumn('offplan_projects', 'documents')) {
                $table->json('documents')->nullable()->after('dld_transactions_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offplan_projects', function (Blueprint $table) {
            $table->dropColumn([
                'price_max', 'area_min', 'area_max', 'area_built_up',
                'rooms', 'units_count', 'completion_status', 'completion_date',
                'investment_score', 'estimated_yield', 'dld_avg_price_sqft',
                'dld_transactions_count', 'documents',
            ]);
        });
    }
};
