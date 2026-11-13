<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->restrictOnDelete();
            $table->string('room_number', 50);
            $table->integer('floor_number');
            $table->enum('status', ['vacant_clean', 'vacant_dirty', 'occupied', 'cleaning', 'out_of_order'])
                ->default('vacant_clean');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'room_number'], 'hotel_room_number_unique');
            $table->index(['hotel_id', 'floor_number', 'status'], 'rooms_hotel_floor_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
