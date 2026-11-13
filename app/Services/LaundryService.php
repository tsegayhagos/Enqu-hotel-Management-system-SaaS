<?php

namespace App\Services;

use App\Enums\FolioStatus;
use App\Enums\LaundryOrderStatus;
use App\Enums\LaundryOrderType;
use App\Enums\LaundryPaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\TransactionType;
use App\Models\Folio;
use App\Models\FolioTransaction;
use App\Models\Hotel;
use App\Models\LaundryOrder;
use App\Models\LaundryService as LaundryServiceCatalog;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Money;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaundryService
{
    public function __construct(
        protected FolioService $folioService
    ) {}

    /**
     * Generate a unique order number in format LND-YYYY-XXXXX.
     */
    public function generateOrderNumber(int $hotelId): string
    {
        $year = now()->format('Y');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $num = str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
            $code = "LND-{$year}-{$num}";

            if (! LaundryOrder::where('order_number', $code)->exists()) {
                return $code;
            }
        }

        return "LND-{$year}-".substr((string) hrtime(true), -5);
    }

    /**
     * Create a new laundry order with line items and optionally auto-post to active room folio.
     *
     * @param  array{
     *     room_id?: ?int,
     *     reservation_id?: ?int,
     *     guest_id?: ?int,
     *     order_type?: string|LaundryOrderType,
     *     is_express?: bool,
     *     payment_method?: string|LaundryPaymentMethod,
     *     special_instructions?: ?string,
     *     items: array<int, array{laundry_service_id: int, quantity: int, notes?: ?string}>,
     *     auto_post_to_folio?: bool
     * }  $data
     */
    public function createOrder(Hotel $hotel, array $data, ?User $processedBy = null): LaundryOrder
    {
        return Tenant::runAs($hotel->id, function () use ($hotel, $data, $processedBy) {
            $itemsInput = $data['items'] ?? [];
            if (empty($itemsInput)) {
                throw ValidationException::withMessages([
                    'items' => 'A laundry order must include at least one service item.',
                ]);
            }

            $orderType = $data['order_type'] ?? LaundryOrderType::GuestValet;
            if (is_string($orderType)) {
                $orderType = LaundryOrderType::tryFrom($orderType) ?? LaundryOrderType::GuestValet;
            }

            $paymentMethod = $data['payment_method'] ?? LaundryPaymentMethod::ChargeToRoom;
            if (is_string($paymentMethod)) {
                $paymentMethod = LaundryPaymentMethod::tryFrom($paymentMethod) ?? LaundryPaymentMethod::ChargeToRoom;
            }

            $roomId = ! empty($data['room_id']) ? (int) $data['room_id'] : null;
            $reservationId = ! empty($data['reservation_id']) ? (int) $data['reservation_id'] : null;
            $guestId = ! empty($data['guest_id']) ? (int) $data['guest_id'] : null;

            // QA Acceptance Criterion 1: Strict validation for charge_to_room
            if ($paymentMethod === LaundryPaymentMethod::ChargeToRoom) {
                if (! $roomId) {
                    throw ValidationException::withMessages([
                        'room_id' => 'A room must be selected when charging laundry to a room folio.',
                    ]);
                }

                $activeReservation = Reservation::where('hotel_id', $hotel->id)
                    ->where('room_id', $roomId)
                    ->where('status', ReservationStatus::CheckedIn)
                    ->latest('id')
                    ->first();

                if (! $activeReservation) {
                    throw ValidationException::withMessages([
                        'room_id' => 'Selected room does not have an active checked-in reservation.',
                    ]);
                }

                $reservationId = $activeReservation->id;
                $guestId = $activeReservation->guest_id;

                $openFolio = Folio::where('hotel_id', $hotel->id)
                    ->where('reservation_id', $reservationId)
                    ->where('status', FolioStatus::Open)
                    ->exists();

                if (! $openFolio) {
                    throw ValidationException::withMessages([
                        'room_id' => 'No open guest folio exists for this checked-in reservation.',
                    ]);
                }
            }

            $isExpress = (bool) ($data['is_express'] ?? false);
            $autoPost = (bool) ($data['auto_post_to_folio'] ?? true);

            return DB::transaction(function () use (
                $hotel,
                $data,
                $itemsInput,
                $orderType,
                $paymentMethod,
                $roomId,
                $reservationId,
                $guestId,
                $isExpress,
                $processedBy,
                $autoPost
            ) {
                $totalCents = 0;
                $preparedItems = [];

                foreach ($itemsInput as $line) {
                    $service = LaundryServiceCatalog::where('hotel_id', $hotel->id)
                        ->findOrFail($line['laundry_service_id']);

                    $quantity = max(1, (int) ($line['quantity'] ?? 1));
                    $unitPriceStr = $isExpress ? $service->express_price : $service->standard_price;
                    $unitPriceCents = Money::toCents($unitPriceStr);
                    $subtotalCents = $unitPriceCents * $quantity;

                    $totalCents += $subtotalCents;

                    $preparedItems[] = [
                        'laundry_service_id' => $service->id,
                        'quantity' => $quantity,
                        'unit_price' => Money::fromCents($unitPriceCents),
                        'subtotal' => Money::fromCents($subtotalCents),
                        'notes' => $line['notes'] ?? null,
                    ];
                }

                $order = LaundryOrder::create([
                    'hotel_id' => $hotel->id,
                    'order_number' => $this->generateOrderNumber($hotel->id),
                    'reservation_id' => $reservationId,
                    'guest_id' => $guestId,
                    'room_id' => $roomId,
                    'order_type' => $orderType,
                    'is_express' => $isExpress,
                    'status' => LaundryOrderStatus::Received,
                    'payment_method' => $paymentMethod,
                    'total_amount' => Money::fromCents($totalCents),
                    'special_instructions' => $data['special_instructions'] ?? null,
                    'received_at' => now(),
                    'processed_by_user_id' => $processedBy?->id ?? auth()->id(),
                ]);

                foreach ($preparedItems as $itemData) {
                    $order->items()->create($itemData);
                }

                if ($autoPost && $paymentMethod === LaundryPaymentMethod::ChargeToRoom && $totalCents > 0) {
                    $this->postToFolio($order, $processedBy);
                }

                return $order->fresh(['items.service', 'room', 'guest', 'reservation', 'processedBy']);
            });
        });
    }

    /**
     * Transition laundry order status along the processing pipeline.
     */
    public function updateStatus(LaundryOrder $order, LaundryOrderStatus|string $newStatus): LaundryOrder
    {
        return Tenant::runAs($order->hotel_id, function () use ($order, $newStatus) {
            $target = is_string($newStatus)
                ? LaundryOrderStatus::from($newStatus)
                : $newStatus;

            if ($order->isDelivered()) {
                throw ValidationException::withMessages([
                    'status' => "Laundry order {$order->order_number} is already delivered and locked.",
                ]);
            }

            if (! $order->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot transition laundry order from {$order->status->label()} to {$target->label()}.",
                ]);
            }

            $order->status = $target;
            if ($target === LaundryOrderStatus::Delivered) {
                $order->delivered_at = now();
            }
            $order->save();

            return $order->fresh(['items.service', 'room', 'guest']);
        });
    }

    /**
     * Replace items on a non-delivered order (disabled once delivered).
     *
     * @param  array<int, array{laundry_service_id: int, quantity: int, notes?: ?string}>  $itemsInput
     */
    public function updateOrderItems(LaundryOrder $order, array $itemsInput): LaundryOrder
    {
        return Tenant::runAs($order->hotel_id, function () use ($order, $itemsInput) {
            if ($order->isDelivered()) {
                throw ValidationException::withMessages([
                    'status' => "Delivered laundry order {$order->order_number} cannot have items modified.",
                ]);
            }

            if (empty($itemsInput)) {
                throw ValidationException::withMessages([
                    'items' => 'A laundry order must contain at least one item.',
                ]);
            }

            return DB::transaction(function () use ($order, $itemsInput) {
                $order->items()->delete();
                $totalCents = 0;

                foreach ($itemsInput as $line) {
                    $service = LaundryServiceCatalog::where('hotel_id', $order->hotel_id)
                        ->findOrFail($line['laundry_service_id']);

                    $quantity = max(1, (int) ($line['quantity'] ?? 1));
                    $unitPriceStr = $order->is_express ? $service->express_price : $service->standard_price;
                    $unitPriceCents = Money::toCents($unitPriceStr);
                    $subtotalCents = $unitPriceCents * $quantity;
                    $totalCents += $subtotalCents;

                    $order->items()->create([
                        'laundry_service_id' => $service->id,
                        'quantity' => $quantity,
                        'unit_price' => Money::fromCents($unitPriceCents),
                        'subtotal' => Money::fromCents($subtotalCents),
                        'notes' => $line['notes'] ?? null,
                    ]);
                }

                $order->total_amount = Money::fromCents($totalCents);
                $order->save();

                return $order->fresh(['items.service']);
            });
        });
    }

    /**
     * Post a laundry order charge directly to the room's active open guest folio.
     */
    public function postToFolio(LaundryOrder $order, ?User $user = null): FolioTransaction
    {
        return Tenant::runAs($order->hotel_id, function () use ($order, $user) {
            if ($order->payment_method !== LaundryPaymentMethod::ChargeToRoom) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Only orders marked Charge to Room can be posted to a guest folio.',
                ]);
            }

            if (! $order->reservation_id) {
                throw ValidationException::withMessages([
                    'reservation_id' => 'Laundry order has no linked checked-in reservation.',
                ]);
            }

            $existing = FolioTransaction::where('hotel_id', $order->hotel_id)
                ->where('reference_id', $order->order_number)
                ->where('transaction_type', TransactionType::Laundry)
                ->first();

            if ($existing) {
                return $existing;
            }

            $folio = Folio::where('hotel_id', $order->hotel_id)
                ->where('reservation_id', $order->reservation_id)
                ->where('status', FolioStatus::Open)
                ->first();

            if (! $folio) {
                throw ValidationException::withMessages([
                    'folio' => 'No open guest folio found for the linked reservation.',
                ]);
            }

            $desc = "Laundry Valet #{$order->order_number}".($order->is_express ? ' (Express)' : '');

            return $this->folioService->addCharge(
                folio: $folio,
                type: TransactionType::Laundry,
                amount: $order->total_amount,
                description: $desc,
                ref: $order->order_number,
                user: $user
            );
        });
    }
}
