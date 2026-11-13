<?php

namespace App\Models;

use App\Enums\FolioStatus;
use App\Enums\TransactionType;
use App\Models\Scopes\HotelScope;
use App\Support\Money;
use App\Support\Tenant;
use App\Traits\BelongsToHotel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folio extends Model
{
    use BelongsToHotel, HasFactory;

    protected $fillable = [
        'hotel_id',
        'reservation_id',
        'guest_id',
        'room_id',
        'folio_number',
        'guest_name',
        'status',
        'total_charges',
        'total_payments',
        'balance',
        'currency',
        'notes',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FolioStatus::class,
            'total_charges' => 'decimal:2',
            'total_payments' => 'decimal:2',
            'balance' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class)->withoutGlobalScope(HotelScope::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class)->withoutGlobalScope(HotelScope::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withoutGlobalScope(HotelScope::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FolioTransaction::class)
            ->withoutGlobalScope(HotelScope::class)
            ->orderBy('posted_at')
            ->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status === FolioStatus::Open;
    }

    public function isSettled(): bool
    {
        return $this->status === FolioStatus::Settled;
    }

    public function isClosed(): bool
    {
        return $this->status === FolioStatus::Closed;
    }

    public function canAcceptCharges(): bool
    {
        return $this->status->canAcceptCharges();
    }

    public function canAcceptPayments(): bool
    {
        return $this->status->canAcceptPayments();
    }

    public function hasZeroBalance(): bool
    {
        return Money::toCents($this->balance) === 0;
    }

    /**
     * Recalculates total_charges, total_payments, and balance using float-free cent arithmetic.
     */
    public function recalculateTotals(): void
    {
        Tenant::runAs($this->hotel_id, function (): void {
            $chargesCents = 0;
            $paymentsCents = 0;

            foreach ($this->transactions()->get() as $tx) {
                $type = $tx->transaction_type;
                $cents = Money::toCents($tx->amount);

                if ($type->isCharge()) {
                    $chargesCents += $cents;
                } elseif ($type === TransactionType::Payment) {
                    $paymentsCents += abs($cents);
                } elseif ($type === TransactionType::Refund) {
                    $paymentsCents -= abs($cents);
                }
            }

            $balanceCents = $chargesCents - $paymentsCents;

            $this->total_charges = Money::fromCents($chargesCents);
            $this->total_payments = Money::fromCents($paymentsCents);
            $this->balance = Money::fromCents($balanceCents);

            if ($this->status !== FolioStatus::Closed && $this->status !== FolioStatus::Void) {
                if ($balanceCents === 0 && $chargesCents > 0) {
                    $this->status = FolioStatus::Settled;
                } elseif ($balanceCents > 0) {
                    $this->status = FolioStatus::Open;
                }
            }

            $this->save();
        });
    }

    /**
     * Generate a unique, race-safe folio number: FOL-YYYY-XXXXX
     */
    public static function generateFolioNumber(int $hotelId): string
    {
        $year = date('Y');
        $prefix = "FOL-{$year}-";

        $lastFolio = static::withoutGlobalScopes()
            ->where('hotel_id', $hotelId)
            ->where('folio_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('folio_number');

        $nextSeq = 1;
        if ($lastFolio && preg_match('/FOL-\d{4}-(\d+)/', $lastFolio, $matches)) {
            $nextSeq = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%05d', $prefix, $nextSeq);
    }
}
