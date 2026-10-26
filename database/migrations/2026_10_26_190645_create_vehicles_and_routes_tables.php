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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['car', 'bus', 'ajala']);
            $table->unsignedInteger('seat_count');
            $table->string('license_plate')->nullable();
            $table->enum('status', ['active', 'maintenance'])->default('active');
            $table->json('photos')->nullable();
            $table->timestamps();
        });

        Schema::create('driver_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('start_point');
            $table->string('first_trip_time'); // HH:MM
            $table->enum('status', ['on_route', 'temporary_route', 'off_route', 'skip_work'])->default('on_route');
            $table->json('stops'); // Ordered stops with real coordinates and fare_ils
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_routes');
        Schema::dropIfExists('vehicles');
    }
};
