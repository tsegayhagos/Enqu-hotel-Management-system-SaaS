<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('base_occupancy')->default(2);
            $table->unsignedInteger('max_occupancy')->default(4);
            $table->decimal('base_price', 10, 2);
            $table->decimal('extra_person_fee', 10, 2)->default(0);
            $table->json('amenities')->nullable(); // e.g. ["Wi-Fi", "Mini Bar", "Sea View"]
            $table->timestamps();

            // Added beyond the spec: slugs and names are unique per hotel.
            $table->unique(['hotel_id', 'slug'], 'room_types_hotel_slug_unique');
            $table->unique(['hotel_id', 'name'], 'room_types_hotel_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
