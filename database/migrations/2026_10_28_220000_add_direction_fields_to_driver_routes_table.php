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
            $table->boolean('is_two_way')->default(true)->after('status');
            $table->string('active_direction', 20)->default('outbound')->after('is_two_way');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_routes', function (Blueprint $table) {
            $table->dropColumn(['is_two_way', 'active_direction']);
        });
    }
};
