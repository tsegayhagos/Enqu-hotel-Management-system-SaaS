<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->string('rate_name', 100); // e.g. "Weekend Surcharge", "Peak Season"
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price_per_night', 10, 2);
            // Added beyond the spec so "weekend overrides" can exist: ISO weekdays 1 (Mon) - 7 (Sun); NULL = every day.
            $table->json('days_of_week')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['hotel_id', 'room_type_id', 'start_date', 'end_date'], 'room_rates_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_rates');
    }
};
