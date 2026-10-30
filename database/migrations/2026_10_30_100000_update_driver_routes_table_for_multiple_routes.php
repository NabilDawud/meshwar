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
        // 1. Drop foreign key constraint so MySQL InnoDB does not block dropping its supporting unique index (Error 1553)
        Schema::table('driver_routes', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
        });

        // 2. Drop the unique index and add standard index to allow multiple routes per driver
        Schema::table('driver_routes', function (Blueprint $table) {
            $table->dropUnique(['driver_id']);
            $table->index('driver_id');
        });

        // 3. Re-add foreign key constraint referencing users(id) and add is_active column
        Schema::table('driver_routes', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('users')->cascadeOnDelete();
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
            $table->dropForeign(['driver_id']);
            $table->dropIndex(['driver_id']);
        });

        Schema::table('driver_routes', function (Blueprint $table) {
            $table->unique('driver_id');
            $table->foreign('driver_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
