<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_hotel_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->date('stat_date');
            $table->unsignedInteger('total_rooms')->default(0);
            $table->unsignedInteger('out_of_order_rooms')->default(0);
            $table->unsignedInteger('available_rooms')->default(0);
            $table->unsignedInteger('occupied_rooms')->default(0);
            $table->decimal('occupancy_rate', 5, 2)->default(0.00);
            $table->decimal('adr', 10, 2)->default(0.00);
            $table->decimal('revpar', 10, 2)->default(0.00);
            $table->decimal('room_revenue', 12, 2)->default(0.00);
            $table->decimal('laundry_revenue', 12, 2)->default(0.00);
            $table->decimal('restaurant_pos_revenue', 12, 2)->default(0.00);
            $table->decimal('minibar_fee_revenue', 12, 2)->default(0.00);
            $table->decimal('tax_collected', 12, 2)->default(0.00);
            $table->decimal('total_gross_revenue', 12, 2)->default(0.00);
            $table->decimal('payments_collected', 12, 2)->default(0.00);
            $table->unsignedInteger('arrivals_count')->default(0);
            $table->unsignedInteger('departures_count')->default(0);
            $table->unsignedInteger('cancellations_count')->default(0);
            $table->unsignedInteger('no_shows_count')->default(0);
            $table->timestamps();

            $table->unique(['hotel_id', 'stat_date']);
            $table->index(['hotel_id', 'stat_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_hotel_stats');
    }
};
