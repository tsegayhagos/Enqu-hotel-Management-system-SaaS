<?php

namespace App\Http\Controllers\Billing;

use App\Enums\FolioStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\PostChargeRequest;
use App\Http\Requests\Billing\RecordPaymentRequest;
use App\Http\Requests\Billing\SplitFolioRequest;
use App\Models\Folio;
use App\Services\FolioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FolioController extends Controller
{
    public function __construct(
        protected FolioService $folioService
    ) {}

    /**
     * Display a listing of folios for the current hotel.
     */
    public function index(Request $request): Response
    {
        $hotel = $request->user()->currentHotel();

        $query = Folio::where('hotel_id', $hotel->id)
            ->with(['room.roomType'])
            ->latest('id');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('folio_number', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $folios = $query->paginate(15)->withQueryString();

        $stats = [
            'total_open' => Folio::where('hotel_id', $hotel->id)->where('status', FolioStatus::Open)->count(),
            'total_settled' => Folio::where('hotel_id', $hotel->id)->where('status', FolioStatus::Settled)->count(),
            'total_closed' => Folio::where('hotel_id', $hotel->id)->where('status', FolioStatus::Closed)->count(),
            'outstanding_balance' => (string) Folio::where('hotel_id', $hotel->id)->where('status', FolioStatus::Open)->sum('balance'),
        ];

        return Inertia::render('Billing/Index', [
            'folios' => $folios,
            'filters' => $request->only(['search', 'status']),
            'stats' => $stats,
        ]);
    }

    /**
     * Show detailed ledger and transactions for a folio.
     */
    public function show(Folio $folio): Response
    {
        $folio->load([
            'room.roomType',
            'transactions.user',
        ]);

        $summary = $this->folioService->getSettlementSummary($folio);

        return Inertia::render('Billing/FolioDetail', [
            'folio' => $folio,
            'summary' => $summary,
        ]);
    }

    /**
     * Post a charge to the folio.
     */
    public function storeCharge(PostChargeRequest $request, Folio $folio): RedirectResponse
    {
        $validated = $request->validated();

        $this->folioService->addCharge(
            folio: $folio,
            type: $validated['transaction_type'],
            amount: $validated['amount'],
            description: $validated['description'],
            ref: $validated['reference_id'] ?? null,
            user: $request->user()
        );

        return back()->with('success', 'Charge posted successfully.');
    }

    /**
     * Record a payment toward the folio.
     */
    public function storePayment(RecordPaymentRequest $request, Folio $folio): RedirectResponse
    {
        $validated = $request->validated();

        $this->folioService->recordPayment(
            folio: $folio,
            amount: $validated['amount'],
            method: $validated['payment_method'],
            ref: $validated['reference_id'] ?? null,
            user: $request->user(),
            description: $validated['description'] ?? null
        );

        return back()->with('success', 'Payment recorded successfully.');
    }

    /**
     * Split selected transactions to a new folio.
     */
    public function split(SplitFolioRequest $request, Folio $folio): RedirectResponse
    {
        $validated = $request->validated();

        $newFolio = $this->folioService->splitFolio(
            originalFolio: $folio,
            transactionIds: $validated['transaction_ids'],
            newFolioGuestName: $validated['new_guest_name'] ?? null,
            user: $request->user()
        );

        return redirect()->route('folios.show', $newFolio->id)->with('success', "Folio successfully split into new folio {$newFolio->folio_number}.");
    }

    /**
     * Close the folio.
     */
    public function close(Request $request, Folio $folio): RedirectResponse
    {
        $this->folioService->closeFolio($folio, $request->user());

        return back()->with('success', "Folio {$folio->folio_number} has been closed.");
    }

    /**
     * Print or download invoice for the folio.
     */
    public function printInvoice(Request $request, Folio $folio)
    {
        $folio->load(['hotel', 'room.roomType', 'transactions.user']);
        $summary = $this->folioService->getSettlementSummary($folio);
        $hotel = $folio->hotel;

        if ($request->query('format') === 'pdf') {
            $pdf = Pdf::loadView('invoices.folio', [
                'folio' => $folio,
                'hotel' => $hotel,
                'summary' => $summary,
            ]);

            return $pdf->download("Invoice-{$folio->folio_number}.pdf");
        }

        return Inertia::render('Billing/InvoicePrint', [
            'folio' => $folio,
            'hotel' => $hotel,
            'summary' => $summary,
        ]);
    }
}
