<?php

namespace App\Services;

use App\Enums\FolioStatus;
use App\Enums\NightAuditStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoomStatus;
use App\Enums\TransactionType;
use App\Models\Folio;
use App\Models\FolioTransaction;
use App\Models\Hotel;
use App\Models\NightAuditLog;
use App\Models\User;
use App\Support\Money;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NightAuditService
{
    public function __construct(
        protected FolioService $folioService
    ) {}

    /**
     * Run the automated night audit for a given hotel and date.
     * Idempotent: Running it twice for the same calendar date will not double charge.
     */
    public function runAudit(Hotel $hotel, ?string $auditDate = null, ?User $executedBy = null): NightAuditLog
    {
        $date = $auditDate ? Carbon::parse($auditDate)->toDateString() : Carbon::yesterday()->toDateString();

        return Tenant::runAs($hotel->id, function () use ($hotel, $date, $executedBy) {
            // 1. Idempotency Check
            $existingLog = NightAuditLog::withoutGlobalScopes()
                ->where('hotel_id', $hotel->id)
                ->where('audit_date', $date)
                ->first();

            if ($existingLog && $existingLog->status === NightAuditStatus::Completed) {
                Log::info("Night audit for hotel {$hotel->id} on {$date} already completed. Skipping.");

                return $existingLog;
            }

            return DB::transaction(function () use ($hotel, $date, $executedBy, $existingLog) {
                $totalRoomRevenueCents = 0;
                $totalTaxCents = 0;
                $taxRatePercent = (float) ($hotel->tax_rate ?? 15.00);

                // Find all active open folios with occupied rooms for this hotel
                $openFolios = Folio::withoutGlobalScopes()
                    ->where('hotel_id', $hotel->id)
                    ->where('status', FolioStatus::Open->value)
                    ->whereNotNull('room_id')
                    ->with(['room.roomType'])
                    ->get();

                $occupiedRoomsCount = 0;
                $nightAuditRef = "NIGHT-AUDIT-{$date}";
                $taxRef = "TAX-{$date}";

                foreach ($openFolios as $folio) {
                    $room = $folio->room;
                    if (! $room || $room->status !== RoomStatus::Occupied) {
                        continue;
                    }

                    $occupiedRoomsCount++;

                    // Check if already posted for this folio on this date
                    $alreadyPosted = FolioTransaction::withoutGlobalScopes()
                        ->where('hotel_id', $hotel->id)
                        ->where('folio_id', $folio->id)
                        ->where('reference_id', $nightAuditRef)
                        ->exists();

                    if (! $alreadyPosted) {
                        // Room rate: standard from room type or base price
                        $roomPrice = $room->roomType?->base_price ?? '100.00';
                        $roomCents = Money::toCents($roomPrice);

                        // Post Room Charge
                        FolioTransaction::create([
                            'hotel_id' => $hotel->id,
                            'folio_id' => $folio->id,
                            'user_id' => $executedBy?->id,
                            'transaction_type' => TransactionType::RoomCharge,
                            'description' => "Night Audit Daily Rate - Room {$room->room_number} ({$date})",
                            'amount' => Money::fromCents($roomCents),
                            'reference_id' => $nightAuditRef,
                            'payment_method' => PaymentMethod::None,
                            'is_night_audit_post' => true,
                            'is_immutable' => true,
                            'posted_at' => now(),
                        ]);

                        $totalRoomRevenueCents += $roomCents;

                        // Calculate and Post Tax (e.g. 15% VAT)
                        $taxCents = (int) round($roomCents * ($taxRatePercent / 100));
                        if ($taxCents > 0) {
                            FolioTransaction::create([
                                'hotel_id' => $hotel->id,
                                'folio_id' => $folio->id,
                                'user_id' => $executedBy?->id,
                                'transaction_type' => TransactionType::Tax,
                                'description' => "Room Tax & VAT ({$taxRatePercent}%) ({$date})",
                                'amount' => Money::fromCents($taxCents),
                                'reference_id' => $taxRef,
                                'payment_method' => PaymentMethod::None,
                                'is_night_audit_post' => true,
                                'is_immutable' => true,
                                'posted_at' => now(),
                            ]);

                            $totalTaxCents += $taxCents;
                        }

                        $folio->recalculateTotals();
                    }
                }

                // Lock all transactions up to current audit date as immutable
                FolioTransaction::withoutGlobalScopes()
                    ->where('hotel_id', $hotel->id)
                    ->where('posted_at', '<=', Carbon::parse($date)->endOfDay())
                    ->where('is_immutable', false)
                    ->update(['is_immutable' => true]);

                // Create or update NightAuditLog
                $log = $existingLog ?? new NightAuditLog;
                $log->hotel_id = $hotel->id;
                $log->audit_date = $date;
                $log->occupied_rooms_count = $occupiedRoomsCount;
                $log->total_room_revenue_posted = Money::fromCents($totalRoomRevenueCents);
                $log->total_tax_posted = Money::fromCents($totalTaxCents);
                $log->status = NightAuditStatus::Completed;
                $log->executed_by_user_id = $executedBy?->id;
                $log->notes = "Night audit completed for {$date}. {$occupiedRoomsCount} room(s) processed.";
                $log->save();

                app(AnalyticsService::class)->snapshotDailyStats($hotel, $date);

                return $log;
            });
        });
    }
}
