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
        Schema::table('driver_routes', function (Blueprint $table) {
            $table->dropUnique(['driver_id']);
            $table->boolean('is_active')->default(true)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_routes', function (Blueprint $table) {
            $table->dropColumn('is_active');
            $table->unique('driver_id');
        });
    }
};
