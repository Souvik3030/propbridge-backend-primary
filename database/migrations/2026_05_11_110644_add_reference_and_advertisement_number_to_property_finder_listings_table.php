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
            $table->string('reference')->nullable()->after('pf_reference');
            $table->string('advertisement_number')->nullable()->after('permit_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_finder_listings', function (Blueprint $table) {
            $table->dropColumn(['reference', 'advertisement_number']);
        });
    }
};
