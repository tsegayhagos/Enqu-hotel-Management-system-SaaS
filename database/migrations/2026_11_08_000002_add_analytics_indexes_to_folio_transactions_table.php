<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folio_transactions', function (Blueprint $table) {
            $table->index(['hotel_id', 'posted_at'], 'folio_tx_hotel_posted_idx');
            $table->index(['hotel_id', 'user_id', 'posted_at'], 'folio_tx_hotel_user_posted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('folio_transactions', function (Blueprint $table) {
            $table->dropIndex('folio_tx_hotel_posted_idx');
            $table->dropIndex('folio_tx_hotel_user_posted_idx');
        });
    }
};
