<?php

namespace App\Services;

use App\Enums\FolioStatus;
use App\Enums\PaymentMethod;
use App\Enums\SystemRole;
use App\Enums\TransactionType;
use App\Models\Folio;
use App\Models\FolioTransaction;
use App\Models\Hotel;
use App\Models\User;
use App\Support\Money;
use App\Support\Tenant;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FolioService
{
    /**
     * Open a new guest folio for a hotel.
     *
     * @param  array{reservation_id?: ?int, guest_id?: ?int, room_id?: ?int, guest_name?: ?string, notes?: ?string, currency?: ?string}  $attributes
     */
    public function openFolio(Hotel $hotel, array $attributes = []): Folio
    {
        return Tenant::runAs($hotel->id, function () use ($hotel, $attributes) {
            return DB::transaction(function () use ($hotel, $attributes) {
                $folioNumber = Folio::generateFolioNumber($hotel->id);

                return Folio::create([
                    'hotel_id' => $hotel->id,
                    'reservation_id' => $attributes['reservation_id'] ?? null,
                    'guest_id' => $attributes['guest_id'] ?? null,
                    'room_id' => $attributes['room_id'] ?? null,
                    'folio_number' => $folioNumber,
                    'guest_name' => $attributes['guest_name'] ?? null,
                    'status' => FolioStatus::Open,
                    'total_charges' => '0.00',
                    'total_payments' => '0.00',
                    'balance' => '0.00',
                    'currency' => $attributes['currency'] ?? $hotel->currency ?? 'USD',
                    'notes' => $attributes['notes'] ?? null,
                ]);
            });
        });
    }

    /**
     * Post a charge to the folio.
     */
    public function addCharge(
        Folio $folio,
        TransactionType|string $type,
        float|int|string $amount,
        string $description,
        ?string $ref = null,
        ?User $user = null
    ): FolioTransaction {
        return Tenant::runAs($folio->hotel_id, function () use ($folio, $type, $amount, $description, $ref, $user) {
            $transactionType = is_string($type) ? TransactionType::from($type) : $type;

            if (! $transactionType->isCharge()) {
                throw new InvalidArgumentException("Transaction type '{$transactionType->value}' is not a valid charge type.");
            }

            $cents = Money::toCents($amount);
            if ($cents <= 0) {
                throw new InvalidArgumentException('Charge amount must be strictly greater than zero.');
            }

            if (! $folio->isOpen()) {
                throw new DomainException("Cannot post charges to folio {$folio->folio_number} with status '{$folio->status->label()}'.");
            }

            return DB::transaction(function () use ($folio, $transactionType, $cents, $description, $ref, $user) {
                $tx = FolioTransaction::create([
                    'hotel_id' => $folio->hotel_id,
                    'folio_id' => $folio->id,
                    'user_id' => $user?->id,
                    'transaction_type' => $transactionType,
                    'description' => $description,
                    'amount' => Money::fromCents($cents),
                    'reference_id' => $ref,
                    'payment_method' => PaymentMethod::None,
                    'is_night_audit_post' => false,
                    'is_immutable' => false,
                    'posted_at' => now(),
                ]);

                $folio->recalculateTotals();

                return $tx;
            });
        });
    }

    /**
     * Record a payment toward the folio.
     */
    public function recordPayment(
        Folio $folio,
        float|int|string $amount,
        PaymentMethod|string $method,
        ?string $ref = null,
        ?User $user = null,
        ?string $description = null
    ): FolioTransaction {
        return Tenant::runAs($folio->hotel_id, function () use ($folio, $amount, $method, $ref, $user, $description) {
            $paymentMethod = is_string($method) ? PaymentMethod::from($method) : $method;
            $cents = Money::toCents($amount);

            if ($cents <= 0) {
                throw new InvalidArgumentException('Payment amount must be strictly greater than zero.');
            }

            if (! $folio->canAcceptPayments()) {
                throw new DomainException("Cannot record payment for folio {$folio->folio_number} with status '{$folio->status->label()}'.");
            }

            return DB::transaction(function () use ($folio, $paymentMethod, $cents, $ref, $user, $description) {
                // Negative amount in transactions table for payments/credits per schema
                $storedAmount = '-'.Money::fromCents($cents);

                $desc = $description ?: "Payment via {$paymentMethod->label()}".($ref ? " [Ref: {$ref}]" : '');

                $tx = FolioTransaction::create([
                    'hotel_id' => $folio->hotel_id,
                    'folio_id' => $folio->id,
                    'user_id' => $user?->id,
                    'transaction_type' => TransactionType::Payment,
                    'description' => $desc,
                    'amount' => $storedAmount,
                    'reference_id' => $ref,
                    'payment_method' => $paymentMethod,
                    'is_night_audit_post' => false,
                    'is_immutable' => false,
                    'posted_at' => now(),
                ]);

                $folio->recalculateTotals();

                return $tx;
            });
        });
    }

    /**
     * Record a refund to the guest.
     */
    public function recordRefund(
        Folio $folio,
        float|int|string $amount,
        PaymentMethod|string $method,
        string $description,
        ?string $ref = null,
        ?User $user = null
    ): FolioTransaction {
        return Tenant::runAs($folio->hotel_id, function () use ($folio, $amount, $method, $description, $ref, $user) {
            $paymentMethod = is_string($method) ? PaymentMethod::from($method) : $method;
            $cents = Money::toCents($amount);

            if ($cents <= 0) {
                throw new InvalidArgumentException('Refund amount must be strictly greater than zero.');
            }

            return DB::transaction(function () use ($folio, $paymentMethod, $cents, $description, $ref, $user) {
                $storedAmount = Money::fromCents($cents);

                $tx = FolioTransaction::create([
                    'hotel_id' => $folio->hotel_id,
                    'folio_id' => $folio->id,
                    'user_id' => $user?->id,
                    'transaction_type' => TransactionType::Refund,
                    'description' => $description,
                    'amount' => $storedAmount,
                    'reference_id' => $ref,
                    'payment_method' => $paymentMethod,
                    'is_night_audit_post' => false,
                    'is_immutable' => false,
                    'posted_at' => now(),
                ]);

                $folio->recalculateTotals();

                return $tx;
            });
        });
    }

    /**
     * Close the folio.
     * Acceptance criteria: Cannot close if balance > 0 unless authorized by a Manager role.
     */
    public function closeFolio(Folio $folio, ?User $authorizingManager = null): Folio
    {
        return Tenant::runAs($folio->hotel_id, function () use ($folio, $authorizingManager) {
            $balanceCents = Money::toCents($folio->balance);

            if ($balanceCents > 0) {
                $isAuthorizedManager = $authorizingManager && (
                    $authorizingManager->is_super_admin ||
                    $authorizingManager->hasRole(SystemRole::HotelManager->value) ||
                    $authorizingManager->hasRole(SystemRole::FrontDesk->value) === false &&
                    ($authorizingManager->hasRole('Hotel Manager') || $authorizingManager->can('hotel.manage'))
                );

                if (! $isAuthorizedManager) {
                    throw new DomainException(
                        "Cannot close folio {$folio->folio_number} with an outstanding balance of {$folio->currency} {$folio->balance} without Manager authorization."
                    );
                }
            }

            $folio->status = FolioStatus::Closed;
            $folio->closed_at = now();
            $folio->save();

            return $folio;
        });
    }

    /**
     * Split selected transactions from one folio into a new folio (e.g. Master Company vs Personal).
     *
     * @param  array<int>  $transactionIds
     */
    public function splitFolio(
        Folio $originalFolio,
        array $transactionIds,
        ?string $newFolioGuestName = null,
        ?User $user = null
    ): Folio {
        return Tenant::runAs($originalFolio->hotel_id, function () use ($originalFolio, $transactionIds, $newFolioGuestName) {
            return DB::transaction(function () use ($originalFolio, $transactionIds, $newFolioGuestName) {
                $transactions = FolioTransaction::where('hotel_id', $originalFolio->hotel_id)
                    ->where('folio_id', $originalFolio->id)
                    ->whereIn('id', $transactionIds)
                    ->get();

                if ($transactions->isEmpty()) {
                    throw new InvalidArgumentException('No valid transactions selected to split.');
                }

                // Create target split folio
                $newFolioNumber = Folio::generateFolioNumber($originalFolio->hotel_id);
                $newFolio = Folio::create([
                    'hotel_id' => $originalFolio->hotel_id,
                    'reservation_id' => $originalFolio->reservation_id,
                    'guest_id' => $originalFolio->guest_id,
                    'room_id' => $originalFolio->room_id,
                    'folio_number' => $newFolioNumber,
                    'guest_name' => $newFolioGuestName ?: "Split from {$originalFolio->folio_number}",
                    'status' => FolioStatus::Open,
                    'total_charges' => '0.00',
                    'total_payments' => '0.00',
                    'balance' => '0.00',
                    'currency' => $originalFolio->currency,
                    'notes' => "Split from Folio {$originalFolio->folio_number}",
                ]);

                // Re-assign transactions to the new folio
                foreach ($transactions as $tx) {
                    $tx->folio_id = $newFolio->id;
                    $tx->save();
                }

                $originalFolio->recalculateTotals();
                $newFolio->recalculateTotals();

                return $newFolio;
            });
        });
    }

    /**
     * Compute a comprehensive settlement breakdown.
     *
     * @return array{
     *   folio_number: string,
     *   status: string,
     *   currency: string,
     *   total_charges: string,
     *   total_payments: string,
     *   balance: string,
     *   charges_by_type: array<string, string>,
     *   payments_by_method: array<string, string>,
     *   is_settled: bool
     * }
     */
    public function getSettlementSummary(Folio $folio): array
    {
        return Tenant::runAs($folio->hotel_id, function () use ($folio) {
            $chargesByType = [];
            $paymentsByMethod = [];

            foreach ($folio->transactions as $tx) {
                $type = $tx->transaction_type;
                $cents = Money::toCents($tx->amount);

                if ($type->isCharge()) {
                    $typeName = $type->label();
                    $chargesByType[$typeName] = ($chargesByType[$typeName] ?? 0) + $cents;
                } elseif ($type === TransactionType::Payment) {
                    $methodName = $tx->payment_method->label();
                    $paymentsByMethod[$methodName] = ($paymentsByMethod[$methodName] ?? 0) + abs($cents);
                }
            }

            return [
                'folio_number' => $folio->folio_number,
                'status' => $folio->status->value,
                'currency' => $folio->currency,
                'total_charges' => $folio->total_charges,
                'total_payments' => $folio->total_payments,
                'balance' => $folio->balance,
                'charges_by_type' => array_map(fn ($c) => Money::fromCents($c), $chargesByType),
                'payments_by_method' => array_map(fn ($c) => Money::fromCents($c), $paymentsByMethod),
                'is_settled' => Money::toCents($folio->balance) === 0,
            ];
        });
    }
}
