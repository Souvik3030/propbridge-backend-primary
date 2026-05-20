<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offplan_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('offplan_projects', 'reference_number')) {
                $table->string('reference_number')->nullable()->after('source_id');
            }
            if (!Schema::hasColumn('offplan_projects', 'title_ar')) {
                $table->string('title_ar')->nullable()->after('title');
            }
            if (!Schema::hasColumn('offplan_projects', 'bathrooms')) {
                $table->integer('bathrooms')->nullable()->after('bedrooms');
            }
            if (!Schema::hasColumn('offplan_projects', 'is_furnished')) {
                $table->boolean('is_furnished')->nullable()->after('bathrooms');
            }
            if (!Schema::hasColumn('offplan_projects', 'area_unit')) {
                $table->string('area_unit')->nullable()->after('area_built_up');
            }
            if (!Schema::hasColumn('offplan_projects', 'permit_number')) {
                $table->string('permit_number')->nullable()->after('completion_date');
            }
            if (!Schema::hasColumn('offplan_projects', 'bayut_url')) {
                $table->text('bayut_url')->nullable()->after('permit_number');
            }
            if (!Schema::hasColumn('offplan_projects', 'keywords')) {
                $table->json('keywords')->nullable()->after('amenities');
            }
            if (!Schema::hasColumn('offplan_projects', 'amenities_ar')) {
                $table->json('amenities_ar')->nullable()->after('keywords');
            }
            if (!Schema::hasColumn('offplan_projects', 'keywords_ar')) {
                $table->json('keywords_ar')->nullable()->after('amenities_ar');
            }
            if (!Schema::hasColumn('offplan_projects', 'agency_payload')) {
                $table->json('agency_payload')->nullable()->after('documents');
            }
            if (!Schema::hasColumn('offplan_projects', 'agent_payload')) {
                $table->json('agent_payload')->nullable()->after('agency_payload');
            }
            if (!Schema::hasColumn('offplan_projects', 'verification_payload')) {
                $table->json('verification_payload')->nullable()->after('agent_payload');
            }
            if (!Schema::hasColumn('offplan_projects', 'legal_payload')) {
                $table->json('legal_payload')->nullable()->after('verification_payload');
            }
            if (!Schema::hasColumn('offplan_projects', 'offplan_payload')) {
                $table->json('offplan_payload')->nullable()->after('legal_payload');
            }
            if (!Schema::hasColumn('offplan_projects', 'raw_payload')) {
                $table->json('raw_payload')->nullable()->after('offplan_payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offplan_projects', function (Blueprint $table) {
            foreach ([
                'reference_number',
                'title_ar',
                'bathrooms',
                'is_furnished',
                'area_unit',
                'permit_number',
                'bayut_url',
                'keywords',
                'amenities_ar',
                'keywords_ar',
                'agency_payload',
                'agent_payload',
                'verification_payload',
                'legal_payload',
                'offplan_payload',
                'raw_payload',
            ] as $column) {
                if (Schema::hasColumn('offplan_projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
