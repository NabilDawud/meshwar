<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('confirmed', 'boarded', 'cancelled_by_driver', 'cancelled_by_rider', 'completed') NOT NULL DEFAULT 'confirmed'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('confirmed', 'cancelled_by_driver', 'cancelled_by_rider', 'completed') NOT NULL DEFAULT 'confirmed'");
        }
    }
};
