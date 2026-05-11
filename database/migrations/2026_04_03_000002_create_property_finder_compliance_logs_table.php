<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_finder_compliance_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('agent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->uuid('property_finder_listing_id')->nullable();
            $table->foreign('property_finder_listing_id', 'pf_comp_listing_fk')
                ->references('id')
                ->on('property_finder_listings')
                ->onDelete('cascade');
            
            $table->string('emirate');
            $table->string('permit_number');
            $table->string('license_number')->nullable();
            
            $table->string('status'); // success, failed, warning
            $table->json('response_body')->nullable();
            $table->json('diffs')->nullable();
            $table->string('source'); // pre_save, schedule, manual
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_finder_compliance_logs');
    }
};
