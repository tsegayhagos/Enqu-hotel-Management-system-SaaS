<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    public function index(Request $request): Response
    {
        $hotel = $request->user()->currentHotel();

        $start = $request->filled('start_date')
            ? Carbon::parse($request->string('start_date')->toString())->startOfDay()
            : Carbon::today()->subDays(29)->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->string('end_date')->toString())->endOfDay()
            : Carbon::today()->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $kpi = $this->analyticsService->getKpiSummary($hotel, $start, $end);
        $trend = $this->analyticsService->getDailyRevenueTrend($hotel, $start, $end);

        $platformSummary = $request->user()->is_super_admin
            ? $this->analyticsService->getPlatformMultiHotelSummary($start, $end)
            : [];

        return Inertia::render('Reports/Index', [
            'kpi' => $kpi,
            'trend' => $trend,
            'platformSummary' => $platformSummary,
            'canExport' => $request->user()->can('report.export'),
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
        ]);
    }

    public function cashierHandover(Request $request): Response
    {
        $hotel = $request->user()->currentHotel();

        $start = $request->filled('start_date')
            ? Carbon::parse($request->string('start_date')->toString())->startOfDay()
            : Carbon::today()->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->string('end_date')->toString())->endOfDay()
            : Carbon::today()->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;

        $report = $this->analyticsService->getCashierHandoverReport($hotel, $start, $end, $userId);
        $staff = $hotel->users()->orderBy('users.name')->get(['users.id', 'users.name', 'users.email']);

        return Inertia::render('Reports/CashierHandover', [
            'report' => $report,
            'staff' => $staff,
            'canExport' => $request->user()->can('report.export'),
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'user_id' => $userId,
            ],
        ]);
    }

    public function occupancyCalendar(Request $request): Response
    {
        $hotel = $request->user()->currentHotel();

        $monthStart = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month')->toString())->startOfMonth()
            : Carbon::today()->startOfMonth();

        $calendar = $this->analyticsService->getOccupancyCalendar($hotel, $monthStart);

        return Inertia::render('Reports/OccupancyCalendar', [
            'calendar' => $calendar,
            'prevMonth' => $monthStart->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $monthStart->copy()->addMonth()->format('Y-m'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $hotel = $request->user()->currentHotel();

        $type = $request->string('type', 'daily_summary')->toString();
        $start = $request->filled('start_date')
            ? Carbon::parse($request->string('start_date')->toString())->startOfDay()
            : Carbon::today()->subDays(29)->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->string('end_date')->toString())->endOfDay()
            : Carbon::today()->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return $this->analyticsService->exportReportCsv($hotel, $type, $start, $end);
    }
}
