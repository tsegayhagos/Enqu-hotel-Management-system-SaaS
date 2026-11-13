<?php

use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Auth\HotelSwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Rates\RoomRateController;
use App\Http\Controllers\Rooms\RoomController;
use App\Http\Controllers\Rooms\RoomStatusController;
use App\Http\Controllers\Rooms\RoomTypeController;
use App\Http\Controllers\Settings\HotelSettingsController;
use App\Http\Controllers\Settings\StaffController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

require __DIR__.'/auth.php';

Route::middleware(['auth', 'tenant'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Hotel chooser / switcher for multi-property staff
    Route::get('/select-hotel', [HotelSwitchController::class, 'create'])->name('hotels.select');
    Route::post('/switch-hotel', [HotelSwitchController::class, 'store'])->name('hotels.switch');

    // Settings
    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::middleware('permission:hotel.manage')->group(function (): void {
            Route::get('hotel', [HotelSettingsController::class, 'edit'])->name('hotel.edit');
            Route::put('hotel', [HotelSettingsController::class, 'update'])->name('hotel.update');
        });

        Route::middleware('permission:staff.manage')->group(function (): void {
            Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
            Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
            Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
            Route::get('staff/{user}/edit', [StaffController::class, 'edit'])->name('staff.edit');
            Route::put('staff/{user}', [StaffController::class, 'update'])->name('staff.update');
            Route::delete('staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');
        });
    });

    // Module 02: Property, rooms & rates
    Route::middleware('permission:room.view')->group(function (): void {
        Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
    });

    Route::middleware('permission:room.manage')->group(function (): void {
        Route::get('rooms/create', [RoomController::class, 'create'])->name('rooms.create');
        Route::post('rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::post('rooms/bulk', [RoomController::class, 'bulkStore'])->name('rooms.bulk');
        Route::put('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::patch('rooms/{room}/status', [RoomStatusController::class, 'update'])->name('rooms.status');

        Route::get('room-types', [RoomTypeController::class, 'index'])->name('room-types.index');
        Route::post('room-types', [RoomTypeController::class, 'store'])->name('room-types.store');
        Route::put('room-types/{roomType}', [RoomTypeController::class, 'update'])->name('room-types.update');
        Route::delete('room-types/{roomType}', [RoomTypeController::class, 'destroy'])->name('room-types.destroy');
    });

    Route::middleware('permission:rate.manage')->group(function (): void {
        Route::get('rates', [RoomRateController::class, 'index'])->name('rates.index');
        Route::post('rates', [RoomRateController::class, 'store'])->name('rates.store');
        Route::put('rates/{rate}', [RoomRateController::class, 'update'])->name('rates.update');
        Route::delete('rates/{rate}', [RoomRateController::class, 'destroy'])->name('rates.destroy');
    });

    // SaaS platform (Super Admin only)
    Route::middleware('super_admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('hotels', [HotelController::class, 'index'])->name('hotels.index');
        Route::get('hotels/create', [HotelController::class, 'create'])->name('hotels.create');
        Route::post('hotels', [HotelController::class, 'store'])->name('hotels.store');
        Route::patch('hotels/{hotel}', [HotelController::class, 'update'])->name('hotels.update');
    });
});
