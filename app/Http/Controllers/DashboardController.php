<?php

namespace App\Http\Controllers;

use App\Enums\FolioStatus;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\LaundryOrderStatus;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceTicketStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\TransactionType;
use App\Models\Hotel;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceTicket;
use App\Models\NightAuditLog;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\Money;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analyticsService): Response
    {
        $hotel = Tenant::hotel();
        $user = $request->user();
        $today = Carbon::today()->toDateString();

        $can = [
            'is_super_admin' => (bool) ($user?->is_super_admin ?? false),
            'view_rooms' => $user?->can('room.view') ?? false,
            'manage_rooms' => $user?->can('room.manage') ?? false,
            'manage_rates' => $user?->can('rate.manage') ?? false,
            'view_front_desk' => $user?->can('guest.view') ?? false,
            'manage_reservations' => $user?->can('reservation.manage') ?? false,
            'checkin' => $user?->can('checkin.create') ?? false,
            'view_laundry' => $user?->can('laundry.view') ?? false,
            'manage_laundry' => $user?->can('laundry.manage') ?? false,
            'perform_housekeeping' => $user?->can('housekeeping.perform') ?? false,
            'view_housekeeping' => $user?->can('housekeeping.view') ?? false,
            'manage_housekeeping' => $user?->can('housekeeping.manage') ?? false,
            'report_maintenance' => $user?->can('maintenance.report') ?? false,
            'manage_maintenance' => $user?->can('maintenance.manage') ?? false,
            'view_folios' => $user?->can('folio.view') ?? false,
            'manage_folios' => $user?->can('folio.manage') ?? false,
            'run_night_audit' => $user?->can('nightaudit.run') ?? false,
            'cashier_report' => $user?->can('cashier.report') ?? false,
            'view_reports' => $user?->can('report.view') ?? false,
            'manage_staff' => $user?->can('staff.manage') ?? false,
            'manage_hotel' => $user?->can('hotel.manage') ?? false,
        ];

        return Inertia::render('Dashboard', [
            'staffCount' => $hotel?->users()->count() ?? 0,
            'businessDate' => $today,
            'capabilities' => $can,
            'roomOverview' => $hotel && ($can['view_rooms'] || $can['view_front_desk'] || $can['view_housekeeping'] || $can['view_reports'])
                ? $this->buildRoomOverview($hotel)
                : null,
            'frontDesk' => $hotel && ($can['view_front_desk'] || $can['manage_reservations'] || $can['checkin'])
                ? $this->buildFrontDeskOverview($hotel, $today)
                : null,
            'housekeeping' => $hotel && ($can['view_housekeeping'] || $can['perform_housekeeping'] || $can['manage_housekeeping'])
                ? $this->buildHousekeepingOverview($hotel, $user, $today)
                : null,
            'maintenance' => $hotel && ($can['report_maintenance'] || $can['manage_maintenance'])
                ? $this->buildMaintenanceOverview($hotel)
                : null,
            'laundry' => $hotel && ($can['view_laundry'] || $can['manage_laundry'])
                ? $this->buildLaundryOverview($hotel)
                : null,
            'finance' => $hotel && ($can['view_folios'] || $can['view_reports'] || $can['run_night_audit'] || $can['cashier_report'])
                ? $this->buildFinanceOverview($hotel, $today)
                : null,
            'platformOverview' => $can['is_super_admin']
                ? $this->buildPlatformOverview($analyticsService)
                : null,
        ]);
    }

    /** @return array<string, int|float> */
    private function buildRoomOverview(Hotel $hotel): array
    {
        $counts = DB::table('rooms')
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $total = (int) $counts->sum();
        $vacantClean = (int) ($counts[RoomStatus::VacantClean->value] ?? 0);
        $vacantDirty = (int) ($counts[RoomStatus::VacantDirty->value] ?? 0);
        $occupied = (int) ($counts[RoomStatus::Occupied->value] ?? 0);
        $cleaning = (int) ($counts[RoomStatus::Cleaning->value] ?? 0);
        $outOfOrder = (int) ($counts[RoomStatus::OutOfOrder->value] ?? 0);
        $available = max(0, $total - $outOfOrder);

        return [
            'total' => $total,
            'vacant_clean' => $vacantClean,
            'vacant_dirty' => $vacantDirty,
            'occupied' => $occupied,
            'cleaning' => $cleaning,
            'out_of_order' => $outOfOrder,
            'available' => $available,
            'occupancy_rate' => $available > 0 ? round(min(100, ($occupied / $available) * 100), 1) : 0.0,
        ];
    }

    /** @return array<string, mixed> */
    private function buildFrontDeskOverview(Hotel $hotel, string $today): array
    {
        $arrivalsDue = Reservation::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->whereDate('check_in_date', '<=', $today)
            ->where('status', ReservationStatus::Confirmed->value)
            ->count();

        $departuresDue = Reservation::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->whereDate('check_out_date', '<=', $today)
            ->where('status', ReservationStatus::CheckedIn->value)
            ->count();

        $inHouse = Reservation::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->where('status', ReservationStatus::CheckedIn->value)
            ->count();

        $upcoming = Reservation::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->whereDate('check_in_date', '>', $today)
            ->where('status', ReservationStatus::Confirmed->value)
            ->count();

        $recentActivity = Reservation::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::CheckedIn->value])
            ->with(['guest:id,first_name,last_name', 'room:id,room_number', 'roomType:id,name'])
            ->orderBy('check_in_date')
            ->limit(5)
            ->get()
            ->map(fn (Reservation $r) => [
                'id' => $r->id,
                'reservation_code' => $r->reservation_code,
                'guest_name' => $r->guest?->full_name ?? 'Guest',
                'room_number' => $r->room?->room_number,
                'room_type' => $r->roomType?->name ?? 'Room',
                'check_in_date' => $r->check_in_date->toDateString(),
                'check_out_date' => $r->check_out_date->toDateString(),
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'status_variant' => $r->status->badgeVariant(),
            ])
            ->values()
            ->all();

        return [
            'arrivals_due' => $arrivalsDue,
            'departures_due' => $departuresDue,
            'in_house' => $inHouse,
            'upcoming' => $upcoming,
            'recent_queue' => $recentActivity,
        ];
    }

    /** @return array<string, int> */
    private function buildHousekeepingOverview(Hotel $hotel, ?User $user, string $today): array
    {
        $counts = DB::table('housekeeping_tasks')
            ->where('hotel_id', $hotel->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $completedToday = HousekeepingTask::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->where('status', HousekeepingTaskStatus::Completed->value)
            ->whereDate('completed_at', $today)
            ->count();

        $myOpenTasks = $user
            ? HousekeepingTask::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->where('assigned_to_user_id', $user->id)
                ->where('status', '!=', HousekeepingTaskStatus::Completed->value)
                ->count()
            : 0;

        return [
            'pending' => (int) ($counts[HousekeepingTaskStatus::Pending->value] ?? 0),
            'in_progress' => (int) ($counts[HousekeepingTaskStatus::InProgress->value] ?? 0),
            'inspected' => (int) ($counts[HousekeepingTaskStatus::Inspected->value] ?? 0),
            'completed_today' => $completedToday,
            'my_open_tasks' => $myOpenTasks,
        ];
    }

    /** @return array<string, mixed> */
    private function buildMaintenanceOverview(Hotel $hotel): array
    {
        $openStatuses = [
            MaintenanceTicketStatus::Open->value,
            MaintenanceTicketStatus::InProgress->value,
        ];

        $openTickets = MaintenanceTicket::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->whereIn('status', $openStatuses)
            ->count();

        $urgentTickets = MaintenanceTicket::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->whereIn('status', $openStatuses)
            ->whereIn('priority', [
                MaintenancePriority::High->value,
                MaintenancePriority::Emergency->value,
            ])
            ->count();

        $blockingOoo = MaintenanceTicket::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->whereIn('status', $openStatuses)
            ->where('blocks_room', true)
            ->count();

        return [
            'open_tickets' => $openTickets,
            'urgent_tickets' => $urgentTickets,
            'blocking_ooo' => $blockingOoo,
        ];
    }

    /** @return array<string, int> */
    private function buildLaundryOverview(Hotel $hotel): array
    {
        $counts = DB::table('laundry_orders')
            ->where('hotel_id', $hotel->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $received = (int) ($counts[LaundryOrderStatus::Received->value] ?? 0);
        $inWash = (int) ($counts[LaundryOrderStatus::InWash->value] ?? 0);
        $ironing = (int) ($counts[LaundryOrderStatus::Ironing->value] ?? 0);
        $ready = (int) ($counts[LaundryOrderStatus::Ready->value] ?? 0);

        return [
            'active_orders' => $received + $inWash + $ironing + $ready,
            'in_process' => $inWash + $ironing,
            'ready_for_delivery' => $ready,
            'delivered' => (int) ($counts[LaundryOrderStatus::Delivered->value] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function buildFinanceOverview(Hotel $hotel, string $today): array
    {
        $openFoliosCount = DB::table('folios')
            ->where('hotel_id', $hotel->id)
            ->where('status', FolioStatus::Open->value)
            ->count();

        $outstandingBalanceRaw = DB::table('folios')
            ->where('hotel_id', $hotel->id)
            ->where('status', FolioStatus::Open->value)
            ->sum('balance');

        $dayStart = Carbon::parse($today)->startOfDay()->toDateTimeString();
        $dayEnd = Carbon::parse($today)->endOfDay()->toDateTimeString();

        $txTotals = DB::table('folio_transactions')
            ->where('hotel_id', $hotel->id)
            ->whereBetween('posted_at', [$dayStart, $dayEnd])
            ->selectRaw('transaction_type, SUM(amount) as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $chargeCents = 0;
        foreach ([
            TransactionType::RoomCharge->value,
            TransactionType::Laundry->value,
            TransactionType::RestaurantPos->value,
            TransactionType::Minibar->value,
            TransactionType::Fee->value,
            TransactionType::Tax->value,
        ] as $type) {
            $chargeCents += Money::toCents($txTotals[$type] ?? 0);
        }

        $paymentCents = abs(Money::toCents($txTotals[TransactionType::Payment->value] ?? 0));
        $refundCents = Money::toCents($txTotals[TransactionType::Refund->value] ?? 0);

        $latestAudit = NightAuditLog::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->latest('audit_date')
            ->first();

        return [
            'currency' => $hotel->currency ?? 'USD',
            'open_folios_count' => $openFoliosCount,
            'outstanding_balance' => Money::fromCents(Money::toCents($outstandingBalanceRaw ?? 0)),
            'today_revenue_posted' => Money::fromCents($chargeCents),
            'today_payments_collected' => Money::fromCents(max(0, $paymentCents - $refundCents)),
            'last_night_audit_date' => $latestAudit?->audit_date?->format('Y-m-d'),
        ];
    }

    /** @return array<string, mixed> */
    private function buildPlatformOverview(AnalyticsService $analyticsService): array
    {
        return [
            'total_hotels' => Hotel::where('is_active', true)->count(),
            'total_staff' => User::where('is_active', true)->count(),
            'hotels' => $analyticsService->getPlatformMultiHotelSummary(
                Carbon::today()->subDays(6)->startOfDay(),
                Carbon::today()->endOfDay()
            ),
        ];
    }
}
