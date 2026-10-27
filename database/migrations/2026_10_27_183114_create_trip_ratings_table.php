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
        Schema::create('trip_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('special_request_id')->nullable()->constrained('special_requests')->nullOnDelete();
            $table->unsignedTinyInteger('rating'); // 1 to 5 stars
            $table->text('comment')->nullable();
            $table->string('client_mutation_id', 64)->nullable()->index();
            $table->timestamps();

            $table->unique(['rider_id', 'booking_id'], 'rider_booking_unique');
            $table->unique(['rider_id', 'special_request_id'], 'rider_special_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_ratings');
    }
};
