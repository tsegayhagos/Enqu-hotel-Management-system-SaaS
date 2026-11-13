<?php

namespace App\Http\Controllers\FrontDesk;

use App\Enums\FolioStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\FrontDesk\CheckOutRequest;
use App\Models\Folio;
use App\Models\Reservation;
use App\Services\CheckOutService;
use App\Services\FolioService;
use Illuminate\Http\RedirectResponse;

class CheckOutController extends Controller
{
    public function __construct(
        protected CheckOutService $checkOutService,
        protected FolioService $folioService
    ) {}

    public function checkOut(CheckOutRequest $request, Reservation $reservation): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        if ($reservation->hotel_id !== $hotel->id) {
            abort(403, 'Unauthorized access to reservation.');
        }

        $validated = $request->validated();
        $managerOverride = $request->boolean('manager_override') && $request->user()->can('checkout.override');

        // Optional on-the-spot balance settlement before final checkout
        if (! empty($validated['settle_balance']) && ! empty($validated['settlement_amount'])) {
            $amount = (float) $validated['settlement_amount'];
            if ($amount > 0) {
                $folio = Folio::where('hotel_id', $hotel->id)
                    ->where('reservation_id', $reservation->id)
                    ->where('status', FolioStatus::Open)
                    ->first();

                if ($folio) {
                    $method = $validated['payment_method'] ?? PaymentMethod::Cash;
                    $this->folioService->recordPayment(
                        folio: $folio,
                        amount: $amount,
                        method: $method,
                        ref: 'CHK-'.$reservation->reservation_code,
                        user: $request->user(),
                        description: 'Checkout settlement payment'
                    );
                }
            }
        }

        $this->checkOutService->checkOut(
            reservation: $reservation,
            managerOverrideBalance: $managerOverride,
            authorizingUser: $request->user()
        );

        return redirect()->back()->with('success', "Check-out completed for {$reservation->guest->full_name}. Room {$reservation->room?->room_number} set to Vacant Dirty.");
    }
}
