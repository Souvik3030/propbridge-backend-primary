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
        Schema::create('offplan_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('source'); // bayut
            $table->unsignedBigInteger('source_id');

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('slug')->nullable();

            $table->decimal('price', 15, 2)->nullable();
            $table->integer('bedrooms')->nullable();

            $table->string('purpose')->nullable();
            $table->string('type_main')->nullable();
            $table->string('type_sub')->nullable();

            $table->foreignUuid('location_id')->constrained('offplan_locations');
            $table->foreignUuid('developer_id')->nullable()->constrained('offplan_developers');

            $table->json('amenities')->nullable();
            $table->json('payment_plans')->nullable();

            $table->boolean('has_ads')->default(false);
            $table->integer('property_ad_count')->default(0);

            $table->timestamps();

            $table->unique(['source', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offplan_projects');
    }
};
