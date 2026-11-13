<?php

use App\Enums\PaymentMethod;
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
        Schema::create('folio_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained('folios')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('transaction_type', 30)->index();
            $table->string('description', 255);
            $table->decimal('amount', 10, 2);
            $table->string('reference_id', 100)->nullable();
            $table->string('payment_method', 30)->default(PaymentMethod::None->value);
            $table->boolean('is_night_audit_post')->default(false);
            $table->boolean('is_immutable')->default(false)->index();
            $table->timestamp('posted_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['hotel_id', 'folio_id']);
            $table->index(['hotel_id', 'transaction_type']);
            $table->index(['folio_id', 'is_immutable']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folio_transactions');
    }
};
