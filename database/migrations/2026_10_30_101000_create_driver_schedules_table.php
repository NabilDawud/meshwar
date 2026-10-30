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
        Schema::create('driver_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Sunday, 1=Monday, ..., 6=Saturday
            $table->foreignId('route_id')->nullable()->constrained('driver_routes')->nullOnDelete();
            $table->string('start_time', 5)->default('07:00'); // HH:MM
            $table->string('end_time', 5)->default('16:00'); // HH:MM
            $table->boolean('is_day_off')->default(false);
            $table->timestamps();

            $table->unique(['driver_id', 'day_of_week']);
            $table->index(['day_of_week', 'is_day_off']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_schedules');
    }
};
