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
        Schema::table('folios', function (Blueprint $table) {
            $table->foreign('guest_id')->references('id')->on('guests')->nullOnDelete();
            $table->foreign('reservation_id')->references('id')->on('reservations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folios', function (Blueprint $table) {
            $table->dropForeign(['guest_id']);
            $table->dropForeign(['reservation_id']);
        });
    }
};
