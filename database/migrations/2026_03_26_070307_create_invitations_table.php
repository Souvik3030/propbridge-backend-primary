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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('token')->unique(); // The secure token used in the email URL
            $table->foreignUuid('company_id')->constrained('companies')->onDelete('cascade');
            $table->enum('role', ['admin', 'agent', 'owner']);
            $table->timestamp('sent_at')->nullable();
            $table->softDeletes();
            
            // FIX: Added ->nullable() to bypass MySQL strict mode zero-date errors
            $table->timestamp('expires_at')->nullable(); 
            
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};