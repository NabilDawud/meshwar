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
            $table->boolean('is_trip_active')->default(false)->after('status');
            $table->decimal('last_latitude', 10, 7)->nullable()->after('is_trip_active');
            $table->decimal('last_longitude', 10, 7)->nullable()->after('last_latitude');
            $table->string('last_landmark')->nullable()->after('last_longitude');
            $table->timestamp('last_position_at')->nullable()->after('last_landmark');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_routes', function (Blueprint $table) {
            $table->dropColumn([
                'is_trip_active',
                'last_latitude',
                'last_longitude',
                'last_landmark',
                'last_position_at',
            ]);
        });
    }
};
