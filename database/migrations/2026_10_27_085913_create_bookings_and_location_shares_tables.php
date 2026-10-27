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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('driver_routes')->cascadeOnDelete();
            $table->unsignedSmallInteger('seats')->default(1);
            $table->string('pickup_stop', 150)->nullable();
            $table->string('dropoff_stop', 150)->nullable();
            $table->decimal('fare_ils', 8, 2)->nullable();
            $table->enum('status', ['confirmed', 'cancelled_by_driver', 'cancelled_by_rider', 'completed'])->default('confirmed');
            $table->text('cancellation_reason')->nullable();
            $table->string('client_mutation_id', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['route_id', 'status']);
            $table->index(['rider_id', 'status']);
        });

        Schema::create('location_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['requested', 'active', 'rejected', 'ended'])->default('requested');
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->timestamp('last_shared_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['driver_id', 'status']);
            $table->index(['rider_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_shares');
        Schema::dropIfExists('bookings');
    }
};
