<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\NightAuditLog;
use App\Services\NightAuditService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NightAuditController extends Controller
{
    public function __construct(
        protected NightAuditService $nightAuditService
    ) {}

    /**
     * Display night audit history and execution dashboard.
     */
    public function index(Request $request): Response
    {
        $hotel = $request->user()->currentHotel();

        $logs = NightAuditLog::where('hotel_id', $hotel->id)
            ->with('executedBy')
            ->latest('audit_date')
            ->paginate(15);

        $latestAudit = NightAuditLog::where('hotel_id', $hotel->id)->latest('audit_date')->first();

        $stats = [
            'last_audit_date' => $latestAudit?->audit_date?->format('Y-m-d') ?? 'None',
            'latest_revenue_posted' => $latestAudit?->total_room_revenue_posted ?? '0.00',
            'latest_tax_posted' => $latestAudit?->total_tax_posted ?? '0.00',
            'latest_rooms_audited' => $latestAudit?->occupied_rooms_count ?? 0,
        ];

        return Inertia::render('NightAudit/Index', [
            'logs' => $logs,
            'stats' => $stats,
            'yesterday_date' => Carbon::yesterday()->toDateString(),
        ]);
    }

    /**
     * Trigger manual night audit run.
     */
    public function runManual(Request $request): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();

        $date = $request->input('audit_date') ?: Carbon::yesterday()->toDateString();

        try {
            $log = $this->nightAuditService->runAudit($hotel, $date, $request->user());

            return back()->with(
                'success',
                "Night audit completed successfully for {$date}. {$log->occupied_rooms_count} room(s) processed with {$hotel->currency} {$log->total_room_revenue_posted} in revenue."
            );
        } catch (\Throwable $e) {
            return back()->with('error', "Night audit failed: {$e->getMessage()}");
        }
    }
}
