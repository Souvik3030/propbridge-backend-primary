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
            if (Schema::hasColumn('property_finder_listings', 'dld_permit_number')) {
                $table->dropColumn('dld_permit_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_finder_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('property_finder_listings', 'dld_permit_number')) {
                $table->string('dld_permit_number')->nullable();
            }
        });
    }
};
