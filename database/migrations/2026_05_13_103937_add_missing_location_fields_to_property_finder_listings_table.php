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
        Schema::table('property_finder_listings', function (Blueprint $table) {
            // Location Details (Human Readable)
            $table->string('pf_location_name')->nullable()->after('location_id');
            $table->string('pf_city')->nullable()->after('pf_location_name');
            $table->string('pf_community')->nullable()->after('pf_city');
            $table->string('pf_subcommunity')->nullable()->after('pf_community');
            $table->string('pf_building')->nullable()->after('pf_subcommunity');
            
            // Geographic data
            $table->string('uae_emirate')->nullable()->after('pf_building');
            $table->string('latitude')->nullable()->after('uae_emirate');
            $table->string('longitude')->nullable()->after('latitude');
            
            // Pricing metadata
            $table->string('price_currency')->default('AED')->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('property_finder_listings', function (Blueprint $table) {
            $table->dropColumn([
                'pf_location_name', 'pf_city', 'pf_community', 
                'pf_subcommunity', 'pf_building', 'uae_emirate', 
                'latitude', 'longitude', 'price_currency'
            ]);
        });
    }
};
