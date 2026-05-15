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
            $table->boolean('portal_pf')->default(false);
            $table->boolean('portal_bayut')->default(false);
            $table->boolean('portal_dubizzle')->default(false);
            $table->boolean('portal_website')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_finder_listings', function (Blueprint $table) {
            $table->dropColumn(['portal_pf', 'portal_bayut', 'portal_dubizzle', 'portal_website']);
        });
    }
};
