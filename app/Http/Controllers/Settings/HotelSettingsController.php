<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateHotelSettingsRequest;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hotel-level settings. Timezone, currency, tax_rate and check-in/out defaults live on
 * the hotels row and are read through Tenant::hotel() by every later module,
 * so a change here propagates immediately to booking calculations.
 */
class HotelSettingsController extends Controller
{
    public function edit(): Response
    {
        $hotel = Tenant::requireHotel();

        return Inertia::render('Settings/Hotel/Edit', [
            'settings' => [
                'name' => $hotel->name,
                'email' => $hotel->email,
                'phone' => $hotel->phone,
                'address' => $hotel->address ?? '',
                'city' => $hotel->city,
                'country' => $hotel->country,
                'currency' => $hotel->currency,
                'timezone' => $hotel->timezone,
                'check_in_time' => substr((string) $hotel->check_in_time, 0, 5),
                'check_out_time' => substr((string) $hotel->check_out_time, 0, 5),
                'tax_rate' => (float) $hotel->tax_rate,
            ],
        ]);
    }

    public function update(UpdateHotelSettingsRequest $request): RedirectResponse
    {
        $hotel = Tenant::requireHotel();
        $hotel->update($request->validated());

        Tenant::forget();

        return back()->with('success', 'Hotel settings saved.');
    }
}
