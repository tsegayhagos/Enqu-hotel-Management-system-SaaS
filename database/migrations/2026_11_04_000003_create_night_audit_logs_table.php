<?php

use App\Enums\NightAuditStatus;
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
        Schema::create('night_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->date('audit_date')->index();
            $table->unsignedInteger('occupied_rooms_count')->default(0);
            $table->decimal('total_room_revenue_posted', 12, 2)->default(0.00);
            $table->decimal('total_tax_posted', 12, 2)->default(0.00);
            $table->string('status', 20)->default(NightAuditStatus::Completed->value);
            $table->foreignId('executed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'audit_date'], 'hotel_audit_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('night_audit_logs');
    }
};
