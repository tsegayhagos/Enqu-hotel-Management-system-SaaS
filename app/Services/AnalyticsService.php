<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\TransactionType;
use App\Models\DailyHotelStat;
use App\Models\FolioTransaction;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Support\Money;
use App\Support\Tenant;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsService
{
    /**
     * Snapshot or refresh daily hotel performance statistics for a calendar date.
     */
    public function snapshotDailyStats(Hotel $hotel, ?string $date = null): DailyHotelStat
    {
        $targetDate = $date ? Carbon::parse($date)->toDateString() : Carbon::yesterday()->toDateString();

        return Tenant::runAs($hotel->id, function () use ($hotel, $targetDate) {
            $dayStart = Carbon::parse($targetDate)->startOfDay();
            $dayEnd = Carbon::parse($targetDate)->endOfDay();

            $roomCounts = DB::table('rooms')
                ->where('hotel_id', $hotel->id)
                ->where('is_active', true)
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status');

            $totalRooms = (int) $roomCounts->sum();
            $oooRooms = (int) ($roomCounts[RoomStatus::OutOfOrder->value] ?? 0);
            $availableRooms = max(0, $totalRooms - $oooRooms);
            $liveOccupiedCount = (int) ($roomCounts[RoomStatus::Occupied->value] ?? 0);

            $reservationOccupied = Reservation::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->whereDate('check_in_date', '<=', $targetDate)
                ->whereDate('check_out_date', '>', $targetDate)
                ->whereIn('status', [
                    ReservationStatus::CheckedIn->value,
                    ReservationStatus::CheckedOut->value,
                ])
                ->count();

            $occupiedRooms = $targetDate === Carbon::today()->toDateString()
                ? max($reservationOccupied, $liveOccupiedCount)
                : ($reservationOccupied > 0 ? $reservationOccupied : ($targetDate === Carbon::yesterday()->toDateString() ? $liveOccupiedCount : 0));

            $txTotals = DB::table('folio_transactions')
                ->where('hotel_id', $hotel->id)
                ->whereBetween('posted_at', [$dayStart->toDateTimeString(), $dayEnd->toDateTimeString()])
                ->selectRaw('transaction_type, SUM(amount) as raw_total')
                ->groupBy('transaction_type')
                ->pluck('raw_total', 'transaction_type');

            $roomCents = Money::toCents($txTotals[TransactionType::RoomCharge->value] ?? 0);
            $laundryCents = Money::toCents($txTotals[TransactionType::Laundry->value] ?? 0);
            $posCents = Money::toCents($txTotals[TransactionType::RestaurantPos->value] ?? 0);
            $minibarCents = Money::toCents($txTotals[TransactionType::Minibar->value] ?? 0);
            $feeCents = Money::toCents($txTotals[TransactionType::Fee->value] ?? 0);
            $taxCents = Money::toCents($txTotals[TransactionType::Tax->value] ?? 0);
            $paymentCents = abs(Money::toCents($txTotals[TransactionType::Payment->value] ?? 0));
            $refundCents = Money::toCents($txTotals[TransactionType::Refund->value] ?? 0);

            $minibarFeeCents = $minibarCents + $feeCents;
            $grossCents = $roomCents + $laundryCents + $posCents + $minibarFeeCents + $taxCents;
            $netPaymentsCents = max(0, $paymentCents - $refundCents);

            $occupancyRate = $availableRooms > 0
                ? round(min(100, ($occupiedRooms / $availableRooms) * 100), 2)
                : 0.00;

            $adrCents = $occupiedRooms > 0 ? (int) round($roomCents / $occupiedRooms) : 0;
            $revparCents = $availableRooms > 0 ? (int) round($roomCents / $availableRooms) : 0;

            $arrivalsCount = Reservation::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->whereDate('check_in_date', $targetDate)
                ->whereIn('status', [ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value])
                ->count();

            $departuresCount = Reservation::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->whereDate('check_out_date', $targetDate)
                ->where('status', ReservationStatus::CheckedOut->value)
                ->count();

            $cancellationsCount = Reservation::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->whereDate('check_in_date', $targetDate)
                ->where('status', ReservationStatus::Cancelled->value)
                ->count();

            $noShowsCount = Reservation::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->whereDate('check_in_date', $targetDate)
                ->where('status', ReservationStatus::NoShow->value)
                ->count();

            $existingStat = DailyHotelStat::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->whereDate('stat_date', $targetDate)
                ->first();

            $payload = [
                'hotel_id' => $hotel->id,
                'stat_date' => $targetDate,
                'total_rooms' => $totalRooms,
                'out_of_order_rooms' => $oooRooms,
                'available_rooms' => $availableRooms,
                'occupied_rooms' => $occupiedRooms,
                'occupancy_rate' => number_format($occupancyRate, 2, '.', ''),
                'adr' => Money::fromCents($adrCents),
                'revpar' => Money::fromCents($revparCents),
                'room_revenue' => Money::fromCents($roomCents),
                'laundry_revenue' => Money::fromCents($laundryCents),
                'restaurant_pos_revenue' => Money::fromCents($posCents),
                'minibar_fee_revenue' => Money::fromCents($minibarFeeCents),
                'tax_collected' => Money::fromCents($taxCents),
                'total_gross_revenue' => Money::fromCents($grossCents),
                'payments_collected' => Money::fromCents($netPaymentsCents),
                'arrivals_count' => $arrivalsCount,
                'departures_count' => $departuresCount,
                'cancellations_count' => $cancellationsCount,
                'no_shows_count' => $noShowsCount,
            ];

            if ($existingStat) {
                $existingStat->update($payload);

                return $existingStat->fresh();
            }

            return DailyHotelStat::withoutGlobalScopes()->create($payload);
        });
    }

    /**
     * Compute executive KPI summary across a date range (Occupancy %, ADR, RevPAR, departmental revenue).
     *
     * @return array<string, mixed>
     */
    public function getKpiSummary(Hotel $hotel, Carbon $start, Carbon $end): array
    {
        return Tenant::runAs($hotel->id, function () use ($hotel, $start, $end) {
            $startDate = $start->copy()->startOfDay();
            $endDate = $end->copy()->endOfDay();
            $todayStr = Carbon::today()->toDateString();

            // 1. Live active room inventory & Out-of-Order exclusions
            $roomCounts = DB::table('rooms')
                ->where('hotel_id', $hotel->id)
                ->where('is_active', true)
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status');

            $totalRooms = (int) $roomCounts->sum();
            $oooRooms = (int) ($roomCounts[RoomStatus::OutOfOrder->value] ?? 0);
            $availableToday = max(0, $totalRooms - $oooRooms);
            $liveOccupiedRooms = (int) ($roomCounts[RoomStatus::Occupied->value] ?? 0);

            // 2. Revenue aggregation (single indexed SQL query)
            $txTotals = DB::table('folio_transactions')
                ->where('hotel_id', $hotel->id)
                ->whereBetween('posted_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
                ->selectRaw('transaction_type, SUM(amount) as raw_total')
                ->groupBy('transaction_type')
                ->pluck('raw_total', 'transaction_type');

            $roomCents = Money::toCents($txTotals[TransactionType::RoomCharge->value] ?? 0);
            $laundryCents = Money::toCents($txTotals[TransactionType::Laundry->value] ?? 0);
            $posCents = Money::toCents($txTotals[TransactionType::RestaurantPos->value] ?? 0);
            $minibarCents = Money::toCents($txTotals[TransactionType::Minibar->value] ?? 0);
            $feeCents = Money::toCents($txTotals[TransactionType::Fee->value] ?? 0);
            $taxCents = Money::toCents($txTotals[TransactionType::Tax->value] ?? 0);
            $paymentCents = abs(Money::toCents($txTotals[TransactionType::Payment->value] ?? 0));
            $refundCents = Money::toCents($txTotals[TransactionType::Refund->value] ?? 0);

            $minibarFeeCents = $minibarCents + $feeCents;
            $grossCents = $roomCents + $laundryCents + $posCents + $minibarFeeCents + $taxCents;
            $netCollectedCents = max(0, $paymentCents - $refundCents);

            // 3. Room-nights sold & available across date range using snapshots + live fallback
            $snapshots = DailyHotelStat::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->whereDate('stat_date', '>=', $startDate->toDateString())
                ->whereDate('stat_date', '<=', $endDate->toDateString())
                ->get()
                ->keyBy(fn (DailyHotelStat $s) => $s->stat_date->format('Y-m-d'));

            $reservations = Reservation::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->whereDate('check_in_date', '<=', $endDate->toDateString())
                ->whereDate('check_out_date', '>', $startDate->toDateString())
                ->whereIn('status', [
                    ReservationStatus::CheckedIn->value,
                    ReservationStatus::CheckedOut->value,
                ])
                ->get(['check_in_date', 'check_out_date']);

            $occupiedRoomNights = 0;
            $availableRoomNights = 0;

            foreach (CarbonPeriod::create($startDate->copy()->startOfDay(), $endDate->copy()->startOfDay()) as $day) {
                $dayStr = $day->toDateString();

                if ($snapshots->has($dayStr) && $dayStr !== $todayStr) {
                    $snap = $snapshots->get($dayStr);
                    $occupiedRoomNights += (int) $snap->occupied_rooms;
                    $availableRoomNights += (int) $snap->available_rooms;

                    continue;
                }

                $resForDay = $reservations->filter(function (Reservation $r) use ($dayStr) {
                    return $r->check_in_date->toDateString() <= $dayStr
                        && $r->check_out_date->toDateString() > $dayStr;
                })->count();

                $occ = $dayStr === $todayStr
                    ? max($resForDay, $liveOccupiedRooms)
                    : $resForDay;

                $occupiedRoomNights += $occ;
                $availableRoomNights += $availableToday;
            }

            $occupancyRate = $availableRoomNights > 0
                ? round(min(100, ($occupiedRoomNights / $availableRoomNights) * 100), 2)
                : 0.00;

            $adrCents = $occupiedRoomNights > 0 ? (int) round($roomCents / $occupiedRoomNights) : 0;
            $revparCents = $availableRoomNights > 0 ? (int) round($roomCents / $availableRoomNights) : 0;

            // 4. Departmental revenue share
            $departments = [
                ['name' => 'Room Revenue', 'cents' => $roomCents, 'color' => '#4f46e5'],
                ['name' => 'Restaurant & POS', 'cents' => $posCents, 'color' => '#0ea5e9'],
                ['name' => 'Laundry & Valet', 'cents' => $laundryCents, 'color' => '#10b981'],
                ['name' => 'Minibar & Fees', 'cents' => $minibarFeeCents, 'color' => '#f59e0b'],
                ['name' => 'Taxes & VAT', 'cents' => $taxCents, 'color' => '#64748b'],
            ];

            $departmentalBreakdown = array_map(function (array $dept) use ($grossCents) {
                $pct = $grossCents > 0 ? round(($dept['cents'] / $grossCents) * 100, 1) : 0.0;

                return [
                    'name' => $dept['name'],
                    'amount' => Money::fromCents($dept['cents']),
                    'amount_numeric' => round($dept['cents'] / 100, 2),
                    'percentage' => $pct,
                    'color' => $dept['color'],
                ];
            }, $departments);

            // 5. Reservation booking sources in date range
            $sourceRows = DB::table('reservations')
                ->where('hotel_id', $hotel->id)
                ->whereDate('check_in_date', '>=', $startDate->toDateString())
                ->whereDate('check_in_date', '<=', $endDate->toDateString())
                ->whereNotIn('status', [ReservationStatus::Cancelled->value])
                ->selectRaw('source, COUNT(*) as bookings_count, SUM(total_amount) as bookings_revenue')
                ->groupBy('source')
                ->get()
                ->map(function ($row) {
                    $enum = ReservationSource::tryFrom((string) $row->source);

                    return [
                        'source' => (string) $row->source,
                        'label' => $enum?->label() ?? ucfirst((string) $row->source),
                        'count' => (int) $row->bookings_count,
                        'revenue' => number_format((float) $row->bookings_revenue, 2, '.', ''),
                    ];
                })
                ->values()
                ->all();

            return [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'currency' => $hotel->currency ?? 'USD',
                'total_rooms' => $totalRooms,
                'out_of_order_rooms' => $oooRooms,
                'available_rooms' => $availableToday,
                'occupied_rooms_now' => $liveOccupiedRooms,
                'occupied_room_nights' => $occupiedRoomNights,
                'available_room_nights' => $availableRoomNights,
                'occupancy_rate' => $occupancyRate,
                'adr' => Money::fromCents($adrCents),
                'revpar' => Money::fromCents($revparCents),
                'room_revenue' => Money::fromCents($roomCents),
                'laundry_revenue' => Money::fromCents($laundryCents),
                'restaurant_pos_revenue' => Money::fromCents($posCents),
                'minibar_fee_revenue' => Money::fromCents($minibarFeeCents),
                'tax_collected' => Money::fromCents($taxCents),
                'total_gross_revenue' => Money::fromCents($grossCents),
                'payments_collected' => Money::fromCents($netCollectedCents),
                'departmental_breakdown' => $departmentalBreakdown,
                'source_breakdown' => $sourceRows,
            ];
        });
    }

    /**
     * Build daily revenue & occupancy series for interactive trend charts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDailyRevenueTrend(Hotel $hotel, Carbon $start, Carbon $end): array
    {
        return Tenant::runAs($hotel->id, function () use ($hotel, $start, $end) {
            $startDate = $start->copy()->startOfDay();
            $endDate = $end->copy()->endOfDay();

            $rows = DB::table('folio_transactions')
                ->where('hotel_id', $hotel->id)
                ->whereBetween('posted_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
                ->selectRaw('DATE(posted_at) as day, transaction_type, SUM(amount) as raw_total')
                ->groupBy('day', 'transaction_type')
                ->get();

            $grouped = [];
            foreach ($rows as $row) {
                $day = (string) $row->day;
                $type = (string) $row->transaction_type;
                $grouped[$day][$type] = Money::toCents($row->raw_total);
            }

            $snapshots = DailyHotelStat::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->whereDate('stat_date', '>=', $startDate->toDateString())
                ->whereDate('stat_date', '<=', $endDate->toDateString())
                ->get()
                ->keyBy(fn (DailyHotelStat $s) => $s->stat_date->format('Y-m-d'));

            $series = [];
            foreach (CarbonPeriod::create($startDate->copy()->startOfDay(), $endDate->copy()->startOfDay()) as $day) {
                $dayStr = $day->toDateString();
                $bucket = $grouped[$dayStr] ?? [];

                $roomCents = $bucket[TransactionType::RoomCharge->value] ?? 0;
                $laundryCents = $bucket[TransactionType::Laundry->value] ?? 0;
                $posCents = $bucket[TransactionType::RestaurantPos->value] ?? 0;
                $otherCents = ($bucket[TransactionType::Minibar->value] ?? 0)
                    + ($bucket[TransactionType::Fee->value] ?? 0)
                    + ($bucket[TransactionType::Tax->value] ?? 0);
                $grossCents = $roomCents + $laundryCents + $posCents + $otherCents;
                $collectedCents = max(
                    0,
                    abs($bucket[TransactionType::Payment->value] ?? 0) - ($bucket[TransactionType::Refund->value] ?? 0)
                );

                $snap = $snapshots->get($dayStr);

                $series[] = [
                    'date' => $dayStr,
                    'label' => $day->format('M d'),
                    'room_revenue' => round($roomCents / 100, 2),
                    'pos_revenue' => round($posCents / 100, 2),
                    'laundry_revenue' => round($laundryCents / 100, 2),
                    'gross_revenue' => round($grossCents / 100, 2),
                    'payments_collected' => round($collectedCents / 100, 2),
                    'occupancy_rate' => $snap ? (float) $snap->occupancy_rate : null,
                ];
            }

            return $series;
        });
    }

    /**
     * Exact cashier / shift handover reconciliation report grouped by staff member & payment method.
     *
     * @return array<string, mixed>
     */
    public function getCashierHandoverReport(Hotel $hotel, Carbon $start, Carbon $end, ?int $userId = null): array
    {
        return Tenant::runAs($hotel->id, function () use ($hotel, $start, $end, $userId) {
            $startDate = $start->copy()->startOfDay();
            $endDate = $end->copy()->endOfDay();

            $groupedQuery = DB::table('folio_transactions')
                ->where('hotel_id', $hotel->id)
                ->whereBetween('posted_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
                ->whereIn('transaction_type', [
                    TransactionType::Payment->value,
                    TransactionType::Refund->value,
                ]);

            if ($userId) {
                $groupedQuery->where('user_id', $userId);
            }

            $grouped = $groupedQuery
                ->selectRaw('user_id, transaction_type, payment_method, SUM(amount) as raw_total, COUNT(*) as tx_count')
                ->groupBy('user_id', 'transaction_type', 'payment_method')
                ->get();

            $baseQuery = FolioTransaction::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->whereBetween('posted_at', [$startDate, $endDate])
                ->whereIn('transaction_type', [
                    TransactionType::Payment->value,
                    TransactionType::Refund->value,
                ]);

            if ($userId) {
                $baseQuery->where('user_id', $userId);
            }

            $staffIds = $grouped->pluck('user_id')->filter()->unique()->values()->all();
            $users = User::whereIn('id', $staffIds)->get(['id', 'name', 'email'])->keyBy('id');

            $cashiers = [];
            $totalsCents = [
                PaymentMethod::Cash->value => 0,
                PaymentMethod::CreditCard->value => 0,
                PaymentMethod::DebitCard->value => 0,
                PaymentMethod::MobileMoney->value => 0,
                PaymentMethod::BankTransfer->value => 0,
                PaymentMethod::Complimentary->value => 0,
                'refunds' => 0,
                'gross_collected' => 0,
                'net_collected' => 0,
                'transaction_count' => 0,
            ];

            foreach ($grouped as $row) {
                $uid = $row->user_id ? (int) $row->user_id : 0;
                if (! isset($cashiers[$uid])) {
                    $u = $uid > 0 ? $users->get($uid) : null;
                    $cashiers[$uid] = [
                        'user_id' => $uid ?: null,
                        'cashier_name' => $u?->name ?? 'System / Automated',
                        'cashier_email' => $u?->email ?? '',
                        PaymentMethod::Cash->value => 0,
                        PaymentMethod::CreditCard->value => 0,
                        PaymentMethod::DebitCard->value => 0,
                        PaymentMethod::MobileMoney->value => 0,
                        PaymentMethod::BankTransfer->value => 0,
                        PaymentMethod::Complimentary->value => 0,
                        'refunds' => 0,
                        'gross_collected' => 0,
                        'net_collected' => 0,
                        'transaction_count' => 0,
                    ];
                }

                $count = (int) $row->tx_count;
                $cashiers[$uid]['transaction_count'] += $count;
                $totalsCents['transaction_count'] += $count;

                if ($row->transaction_type === TransactionType::Payment->value) {
                    $amountCents = abs(Money::toCents($row->raw_total));
                    $methodKey = (string) ($row->payment_method ?? PaymentMethod::Cash->value);
                    if (! isset($cashiers[$uid][$methodKey])) {
                        $methodKey = PaymentMethod::Cash->value;
                    }
                    $cashiers[$uid][$methodKey] += $amountCents;
                    $cashiers[$uid]['gross_collected'] += $amountCents;
                    $totalsCents[$methodKey] += $amountCents;
                    $totalsCents['gross_collected'] += $amountCents;
                } else {
                    $refundCents = abs(Money::toCents($row->raw_total));
                    $cashiers[$uid]['refunds'] += $refundCents;
                    $totalsCents['refunds'] += $refundCents;
                }
            }

            $formattedCashiers = array_values(array_map(function (array $entry) {
                $netCents = $entry['gross_collected'] - $entry['refunds'];

                return [
                    'user_id' => $entry['user_id'],
                    'cashier_name' => $entry['cashier_name'],
                    'cashier_email' => $entry['cashier_email'],
                    'cash' => Money::fromCents($entry[PaymentMethod::Cash->value]),
                    'card' => Money::fromCents($entry[PaymentMethod::CreditCard->value] + $entry[PaymentMethod::DebitCard->value]),
                    'credit_card' => Money::fromCents($entry[PaymentMethod::CreditCard->value]),
                    'debit_card' => Money::fromCents($entry[PaymentMethod::DebitCard->value]),
                    'mobile_money' => Money::fromCents($entry[PaymentMethod::MobileMoney->value]),
                    'bank_transfer' => Money::fromCents($entry[PaymentMethod::BankTransfer->value]),
                    'complimentary' => Money::fromCents($entry[PaymentMethod::Complimentary->value]),
                    'refunds' => Money::fromCents($entry['refunds']),
                    'gross_collected' => Money::fromCents($entry['gross_collected']),
                    'net_collected' => Money::fromCents($netCents),
                    'transaction_count' => $entry['transaction_count'],
                ];
            }, $cashiers));

            $totalsCents['net_collected'] = $totalsCents['gross_collected'] - $totalsCents['refunds'];

            $recentTransactions = (clone $baseQuery)
                ->with(['user:id,name', 'folio:id,folio_number,guest_name,room_id', 'folio.room:id,room_number'])
                ->latest('posted_at')
                ->take(100)
                ->get()
                ->map(fn (FolioTransaction $tx) => [
                    'id' => $tx->id,
                    'posted_at' => $tx->posted_at->toDateTimeString(),
                    'cashier' => $tx->user?->name ?? 'System',
                    'folio_number' => $tx->folio?->folio_number ?? '—',
                    'guest_name' => $tx->folio?->guest_name ?? 'Guest',
                    'room_number' => $tx->folio?->room?->room_number ?? '—',
                    'transaction_type' => $tx->transaction_type->value,
                    'payment_method' => $tx->payment_method->label(),
                    'description' => $tx->description,
                    'reference_id' => $tx->reference_id,
                    'amount' => Money::fromCents(abs(Money::toCents($tx->amount))),
                    'is_refund' => $tx->transaction_type === TransactionType::Refund,
                ]);

            return [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'currency' => $hotel->currency ?? 'USD',
                'by_cashier' => $formattedCashiers,
                'totals' => [
                    'cash' => Money::fromCents($totalsCents[PaymentMethod::Cash->value]),
                    'card' => Money::fromCents($totalsCents[PaymentMethod::CreditCard->value] + $totalsCents[PaymentMethod::DebitCard->value]),
                    'mobile_money' => Money::fromCents($totalsCents[PaymentMethod::MobileMoney->value]),
                    'bank_transfer' => Money::fromCents($totalsCents[PaymentMethod::BankTransfer->value]),
                    'complimentary' => Money::fromCents($totalsCents[PaymentMethod::Complimentary->value]),
                    'refunds' => Money::fromCents($totalsCents['refunds']),
                    'gross_collected' => Money::fromCents($totalsCents['gross_collected']),
                    'net_collected' => Money::fromCents($totalsCents['net_collected']),
                    'transaction_count' => $totalsCents['transaction_count'],
                ],
                'transactions' => $recentTransactions,
            ];
        });
    }

    /**
     * Build monthly occupancy & tape-chart overview.
     *
     * @return array<string, mixed>
     */
    public function getOccupancyCalendar(Hotel $hotel, Carbon $monthStart): array
    {
        return Tenant::runAs($hotel->id, function () use ($hotel, $monthStart) {
            $firstDay = $monthStart->copy()->startOfMonth();
            $lastDay = $monthStart->copy()->endOfMonth();

            $rooms = Room::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->where('is_active', true)
                ->with('roomType:id,name')
                ->orderBy('floor_number')
                ->orderBy('room_number')
                ->get(['id', 'room_type_id', 'room_number', 'floor_number', 'status']);

            $totalRooms = $rooms->count();
            $oooRooms = $rooms->where('status', RoomStatus::OutOfOrder)->count();
            $availableRooms = max(0, $totalRooms - $oooRooms);

            $reservations = Reservation::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->whereDate('check_in_date', '<=', $lastDay->toDateString())
                ->whereDate('check_out_date', '>', $firstDay->toDateString())
                ->whereIn('status', [
                    ReservationStatus::Confirmed->value,
                    ReservationStatus::CheckedIn->value,
                    ReservationStatus::CheckedOut->value,
                ])
                ->get(['id', 'room_id', 'check_in_date', 'check_out_date', 'status']);

            $days = [];
            foreach (CarbonPeriod::create($firstDay, $lastDay) as $day) {
                $dayStr = $day->toDateString();
                $activeOnDay = $reservations->filter(
                    fn (Reservation $r) => $r->check_in_date->toDateString() <= $dayStr
                        && $r->check_out_date->toDateString() > $dayStr
                );

                $arrivals = $reservations->filter(
                    fn (Reservation $r) => $r->check_in_date->toDateString() === $dayStr
                )->count();

                $departures = $reservations->filter(
                    fn (Reservation $r) => $r->check_out_date->toDateString() === $dayStr
                )->count();

                $occupiedCount = $activeOnDay->count();
                $occPct = $availableRooms > 0 ? round(min(100, ($occupiedCount / $availableRooms) * 100), 1) : 0.0;

                $days[] = [
                    'date' => $dayStr,
                    'day' => (int) $day->format('j'),
                    'weekday' => $day->format('D'),
                    'occupied' => $occupiedCount,
                    'available' => $availableRooms,
                    'occupancy_rate' => $occPct,
                    'arrivals' => $arrivals,
                    'departures' => $departures,
                ];
            }

            return [
                'month' => $firstDay->format('Y-m'),
                'month_label' => $firstDay->format('F Y'),
                'total_rooms' => $totalRooms,
                'out_of_order_rooms' => $oooRooms,
                'available_rooms' => $availableRooms,
                'days' => $days,
            ];
        });
    }

    /**
     * SaaS Super Admin: Cross-property KPI summary comparing every active hotel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPlatformMultiHotelSummary(Carbon $start, Carbon $end): array
    {
        $hotels = Hotel::where('is_active', true)->orderBy('name')->get();

        return $hotels->map(function (Hotel $hotel) use ($start, $end) {
            $summary = $this->getKpiSummary($hotel, $start, $end);

            return [
                'hotel_id' => $hotel->id,
                'hotel_name' => $hotel->name,
                'city' => $hotel->city,
                'currency' => $summary['currency'],
                'total_rooms' => $summary['total_rooms'],
                'out_of_order_rooms' => $summary['out_of_order_rooms'],
                'occupancy_rate' => $summary['occupancy_rate'],
                'adr' => $summary['adr'],
                'revpar' => $summary['revpar'],
                'total_gross_revenue' => $summary['total_gross_revenue'],
                'payments_collected' => $summary['payments_collected'],
            ];
        })->values()->all();
    }

    /**
     * Stream a CSV export of financial, departmental, or cashier handover reports.
     */
    public function exportReportCsv(Hotel $hotel, string $reportType, Carbon $start, Carbon $end): StreamedResponse
    {
        $safeType = preg_replace('/[^a-z0-9_-]/i', '', $reportType) ?: 'daily_summary';
        $filename = "{$hotel->slug}-{$safeType}-{$start->toDateString()}_to_{$end->toDateString()}.csv";

        return response()->streamDownload(function () use ($hotel, $safeType, $start, $end): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for clean Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            if ($safeType === 'cashier_handover') {
                $report = $this->getCashierHandoverReport($hotel, $start, $end);
                fputcsv($handle, ['Cashier Name', 'Email', 'Cash', 'Card', 'Mobile Money', 'Bank Transfer', 'Complimentary', 'Refunds', 'Net Collected', 'Transactions']);
                foreach ($report['by_cashier'] as $row) {
                    fputcsv($handle, [
                        $row['cashier_name'],
                        $row['cashier_email'],
                        $row['cash'],
                        $row['card'],
                        $row['mobile_money'],
                        $row['bank_transfer'],
                        $row['complimentary'],
                        $row['refunds'],
                        $row['net_collected'],
                        $row['transaction_count'],
                    ]);
                }
            } elseif ($safeType === 'departmental_revenue') {
                $kpi = $this->getKpiSummary($hotel, $start, $end);
                fputcsv($handle, ['Department', 'Currency', 'Amount', 'Share %']);
                foreach ($kpi['departmental_breakdown'] as $dept) {
                    fputcsv($handle, [
                        $dept['name'],
                        $kpi['currency'],
                        $dept['amount'],
                        $dept['percentage'].'%',
                    ]);
                }
            } else {
                $trend = $this->getDailyRevenueTrend($hotel, $start, $end);
                fputcsv($handle, ['Date', 'Currency', 'Room Revenue', 'Restaurant/POS Revenue', 'Laundry Revenue', 'Total Gross Revenue', 'Payments Collected', 'Occupancy %']);
                foreach ($trend as $day) {
                    fputcsv($handle, [
                        $day['date'],
                        $hotel->currency ?? 'USD',
                        number_format((float) $day['room_revenue'], 2, '.', ''),
                        number_format((float) $day['pos_revenue'], 2, '.', ''),
                        number_format((float) $day['laundry_revenue'], 2, '.', ''),
                        number_format((float) $day['gross_revenue'], 2, '.', ''),
                        number_format((float) $day['payments_collected'], 2, '.', ''),
                        $day['occupancy_rate'] !== null ? $day['occupancy_rate'].'%' : 'Live',
                    ]);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
