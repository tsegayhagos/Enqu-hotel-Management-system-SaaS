<?php

use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Auth\HotelSwitchController;
use App\Http\Controllers\DashboardController;
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

    // SaaS platform (Super Admin only)
    Route::middleware('super_admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('hotels', [HotelController::class, 'index'])->name('hotels.index');
        Route::get('hotels/create', [HotelController::class, 'create'])->name('hotels.create');
        Route::post('hotels', [HotelController::class, 'store'])->name('hotels.store');
        Route::patch('hotels/{hotel}', [HotelController::class, 'update'])->name('hotels.update');
    });
});
