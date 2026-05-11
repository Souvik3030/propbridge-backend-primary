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
            $table->boolean('is_exempt_area')->default(false)->after('permit_number');
            $table->string('compliance_status')->default('pending')->after('is_exempt_area');
            $table->boolean('can_publish')->default(false)->after('compliance_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_finder_listings', function (Blueprint $table) {
            $table->dropColumn(['is_exempt_area', 'compliance_status', 'can_publish']);
        });
    }
};
