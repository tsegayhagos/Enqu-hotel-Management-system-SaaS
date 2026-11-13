<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laundry_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('order_number', 30)->unique();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('order_type', 30)->default('guest_valet');
            $table->boolean('is_express')->default(false);
            $table->string('status', 30)->default('received');
            $table->string('payment_method', 30)->default('charge_to_room');
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->text('special_instructions')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
            $table->index(['hotel_id', 'order_type']);
            $table->index(['hotel_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laundry_orders');
    }
};
