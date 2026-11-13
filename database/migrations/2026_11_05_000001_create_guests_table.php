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
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50);
            $table->string('id_type', 30);
            $table->string('id_number', 100);
            $table->string('nationality', 100)->default('Unknown');
            $table->string('car_plate_number', 50)->nullable();
            $table->string('vip_level', 30)->default('standard');
            $table->boolean('is_blacklisted')->default(false);
            $table->text('blacklist_reason')->nullable();
            $table->unsignedInteger('total_stays_count')->default(0);
            $table->text('special_preferences')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'phone', 'email', 'id_number'], 'guest_search_idx');
            $table->index(['hotel_id', 'is_blacklisted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
