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
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('slug', 100)->unique();
            $table->string('logo_url', 500)->nullable();
            $table->enum('plan', ['free', 'pro', 'enterprise'])->default('free');
            // PropertyFinder API v2 — X-Auth-Token (primary)
            $table->text('pf_api_token')->nullable();          // encrypted
            // PropertyFinder legacy OAuth (backward compat)
            $table->string('pf_client_id')->nullable();
            $table->text('pf_client_secret')->nullable();      // encrypted, text for encrypted size
            $table->text('pf_webhook_secret')->nullable();     // encrypted, text for encrypted size
            $table->boolean('pf_enabled')->default(false);
            $table->text('bitrix_oauth_token')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
