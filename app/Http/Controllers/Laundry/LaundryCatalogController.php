<?php

namespace App\Http\Controllers\Laundry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Laundry\StoreLaundryCatalogRequest;
use App\Models\LaundryService as LaundryServiceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LaundryCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $hotel = $request->user()->currentHotel();

        $services = LaundryServiceCatalog::where('hotel_id', $hotel->id)
            ->orderBy('category')
            ->orderBy('item_name')
            ->get();

        return Inertia::render('Laundry/Catalog/Index', [
            'services' => $services,
            'currency' => $hotel->currency ?? 'USD',
        ]);
    }

    public function store(StoreLaundryCatalogRequest $request): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();

        $service = LaundryServiceCatalog::create(array_merge($request->validated(), [
            'hotel_id' => $hotel->id,
        ]));

        return redirect()->back()->with('success', "Catalog item '{$service->item_name}' created.");
    }

    public function update(StoreLaundryCatalogRequest $request, LaundryServiceCatalog $service): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        if ($service->hotel_id !== $hotel->id) {
            abort(403, 'Unauthorized access to catalog item.');
        }

        $service->update($request->validated());

        return redirect()->back()->with('success', "Catalog item '{$service->item_name}' updated.");
    }

    public function destroy(Request $request, LaundryServiceCatalog $service): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        if ($service->hotel_id !== $hotel->id) {
            abort(403, 'Unauthorized access to catalog item.');
        }

        if ($service->orderItems()->exists()) {
            $service->update(['is_active' => false]);

            return redirect()->back()->with('success', "Catalog item '{$service->item_name}' deactivated (existing orders reference it).");
        }

        $service->delete();

        return redirect()->back()->with('success', "Catalog item '{$service->item_name}' deleted.");
    }
}
