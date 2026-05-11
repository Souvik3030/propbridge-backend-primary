<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CONSOLIDATED migration — single source of truth for property_finder_listings.
 *
 * Includes ALL PF API v2 fields from both the original create and the v2 additions:
 *   Section 4a — emirate-based fields (permit_number, dld_permit_number, building_name)
 *   Section 4b — listing type / category fields (rent_frequency, off-plan fields, etc.)
 *   Section 4c — property type fields (plot_size, private_pool, hotel_name, fitted, zoning)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_finder_listings', function (Blueprint $table) {

            // ── Identifiers ─────────────────────────────────────────────────────
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('agent_id')->nullable()->constrained('users')->onDelete('set null');

            // PF API identifiers (populated after POST /listings succeeds)
            $table->string('pf_id')->nullable()->unique()->index();       // PF internal listing ID
            $table->string('pf_reference')->nullable();                   // PF auto-ref e.g. PF-1234567
            $table->string('pf_listing_url')->nullable();                 // Public URL on propertyfinder.ae

            // ── Location ────────────────────────────────────────────────────────
            $table->integer('location_id')->nullable()->index();          // PF API location ID from GET /locations
            $table->integer('pf_location_id')->nullable();                // Legacy compat alias
            $table->string('emirate')->nullable()->index();               // Human-readable key (legacy)
            $table->unsignedTinyInteger('emirate_id')->nullable()->index(); // PF numeric ID (1=Dubai … 7=UAQ)

            // ── Permits & Compliance ─────────────────────────────────────────────
            $table->string('permit_number')->nullable()->index();
            $table->string('permit_type')->nullable();
            $table->string('license_number')->nullable()->index();
            $table->string('building_name')->nullable();                  // Required: Dubai (1) + Abu Dhabi (2)
            $table->string('dld_permit_number')->nullable();              // Required: Dubai + sale

            // ── Classification ───────────────────────────────────────────────────
            $table->string('listing_type')->nullable()->index();          // sale | rent  (replaces purpose)
            $table->string('property_type')->nullable();                  // apartment | villa | office | …
            $table->string('category')->nullable();                       // residential | commercial | off_plan
            $table->string('project_status')->nullable();                 // off_plan | completed | …

            // Legacy compat fields (keep so old code doesn't break)
            $table->string('purpose')->nullable();
            $table->string('type')->nullable();

            // ── Titles & Descriptions ────────────────────────────────────────────
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();

            // ── Pricing ──────────────────────────────────────────────────────────
            $table->decimal('price', 15, 2)->default(0);
            $table->boolean('price_on_request')->default(false);
            $table->string('ownership_type')->nullable();                 // freehold | leasehold (sale)

            // ── Size / Area ──────────────────────────────────────────────────────
            $table->decimal('size', 10, 2)->nullable();                   // Built-up area (sqft)
            $table->string('size_unit')->default('sqft');
            $table->decimal('plot_size_sqft', 12, 2)->nullable();         // Required: villa | townhouse | land

            // ── Property Specs ───────────────────────────────────────────────────
            $table->integer('bedrooms')->nullable();                      // 0 = studio
            $table->integer('bathrooms')->nullable();
            $table->integer('floor_number')->nullable();                  // 0 = ground
            $table->unsignedSmallInteger('number_of_floors')->nullable();
            $table->boolean('private_pool')->default(false);              // Required: penthouse
            $table->string('hotel_name')->nullable();                     // Required: hotel_apartment
            $table->unsignedSmallInteger('parking')->nullable();
            $table->string('furnished')->nullable();                      // furnished | unfurnished | partly_furnished

            // ── Rental Specific (Section 4b — listing_type = rent) ───────────────
            $table->string('rent_frequency')->nullable();                 // yearly | monthly | weekly | daily
            $table->unsignedTinyInteger('cheques')->nullable();           // 1–12
            $table->date('available_from')->nullable();

            // ── Commercial Specific (Section 4c — office | retail | warehouse) ────
            $table->string('fitted')->nullable();                         // yes | no | partially

            // ── Land / Plot Specific (Section 4c — land) ────────────────────────
            $table->string('zoning_type')->nullable();                    // residential | commercial | mixed | industrial

            // ── Off-Plan Specific (Section 4b — category = off_plan) ─────────────
            $table->string('developer_name')->nullable();
            $table->string('project_name')->nullable();
            $table->date('completion_date')->nullable();
            $table->text('payment_plan')->nullable();

            // ── Media ────────────────────────────────────────────────────────────
            $table->json('images')->nullable();
            $table->json('amenities')->nullable();
            $table->string('virtual_tour', 1000)->nullable();             // Matterport / 360 tour URL
            $table->string('floor_plan', 1000)->nullable();               // Floor plan image URL

            // ── Status & Workflow ─────────────────────────────────────────────────
            // PF API v2 statuses: draft | active | under_review | inactive | compliance_failed
            $table->string('status')->default('draft')->index();
            $table->string('unpublish_reason')->nullable();               // sold | rented | duplicate | …

            // ── Compliance Tracking ───────────────────────────────────────────────
            $table->json('compliance_snapshot')->nullable();              // Full GET /compliance response
            $table->json('validation_diffs')->nullable();                 // Local pre-validation errors
            $table->timestamp('last_compliance_check_at')->nullable();

            // ── Lifecycle Timestamps ──────────────────────────────────────────────
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_finder_listings');
    }
};
