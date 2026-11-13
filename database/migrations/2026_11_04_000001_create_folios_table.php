<?php

use App\Enums\FolioStatus;
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
        Schema::create('folios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->unsignedBigInteger('reservation_id')->nullable()->index();
            $table->unsignedBigInteger('guest_id')->nullable()->index();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('folio_number', 30)->unique();
            $table->string('guest_name')->nullable();
            $table->string('status', 20)->default(FolioStatus::Open->value)->index();
            $table->decimal('total_charges', 10, 2)->default(0.00);
            $table->decimal('total_payments', 10, 2)->default(0.00);
            $table->decimal('balance', 10, 2)->default(0.00);
            $table->string('currency', 10)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
            $table->index(['hotel_id', 'reservation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folios');
    }
};
