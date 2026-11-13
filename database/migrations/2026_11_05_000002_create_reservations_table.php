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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('reservation_code', 30)->unique();
            $table->foreignId('guest_id')->constrained('guests')->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->restrictOnDelete();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->timestamp('actual_check_in_at')->nullable();
            $table->timestamp('actual_check_out_at')->nullable();
            $table->unsignedInteger('adults_count')->default(1);
            $table->unsignedInteger('children_count')->default(0);
            $table->decimal('daily_rate', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('advance_deposit_paid', 10, 2)->default(0.00);
            $table->string('keycard_number', 50)->nullable();
            $table->string('status', 30)->default('confirmed');
            $table->string('source', 30)->default('walk_in');
            $table->text('special_requests')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
            $table->index(['hotel_id', 'check_in_date']);
            $table->index(['hotel_id', 'check_out_date']);
            $table->index(['hotel_id', 'room_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
