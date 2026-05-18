<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offplan_developers', function (Blueprint $table) {
            if (!Schema::hasColumn('offplan_developers', 'project_count')) {
                $table->integer('project_count')->default(0)->after('logo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offplan_developers', function (Blueprint $table) {
            $table->dropColumn('project_count');
        });
    }
};
