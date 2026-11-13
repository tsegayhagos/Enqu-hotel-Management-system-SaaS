<?php

use App\Http\Controllers\Api\PosChargeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::middleware('permission:pos.charge')->group(function (): void {
        Route::get('/pos/active-rooms', [PosChargeController::class, 'activeRooms'])->name('api.pos.active-rooms');
        Route::post('/pos/charge-to-room', [PosChargeController::class, 'chargeToRoom'])->name('api.pos.charge-to-room');
    });
});
