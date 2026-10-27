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
            DB::statement("ALTER TABLE special_requests MODIFY COLUMN status ENUM('open', 'accepted', 'completed', 'cancelled') NOT NULL DEFAULT 'open'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE special_requests MODIFY COLUMN status ENUM('open', 'accepted', 'cancelled') NOT NULL DEFAULT 'open'");
        }
    }
};
