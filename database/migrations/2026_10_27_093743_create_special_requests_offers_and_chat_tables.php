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
        Schema::create('special_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->string('origin_name', 150);
            $table->string('destination_name', 150);
            $table->date('departure_date');
            $table->string('departure_window', 100);
            $table->unsignedSmallInteger('seats_needed')->default(1);
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'accepted', 'completed', 'cancelled'])->default('open');
            $table->unsignedBigInteger('accepted_offer_id')->nullable();
            $table->string('client_mutation_id', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['rider_id', 'status']);
            $table->index('status');
        });

        Schema::create('special_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('special_requests')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('price_ils', 8, 2);
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->string('client_mutation_id', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['request_id', 'status']);
            $table->index(['driver_id', 'status']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('special_offers')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->string('client_mutation_id', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['offer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('special_offers');
        Schema::dropIfExists('special_requests');
    }
};
