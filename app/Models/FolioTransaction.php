<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Traits\BelongsToHotel;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolioTransaction extends Model
{
    use BelongsToHotel, HasFactory;

    protected $fillable = [
        'hotel_id',
        'folio_id',
        'user_id',
        'transaction_type',
        'description',
        'amount',
        'reference_id',
        'payment_method',
        'is_night_audit_post',
        'is_immutable',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionType::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'is_night_audit_post' => 'boolean',
            'is_immutable' => 'boolean',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (FolioTransaction $transaction) {
            if ($transaction->getOriginal('is_immutable')) {
                // Allow only changing is_immutable if explicitly updating state, otherwise block edits
                if (! $transaction->isDirty('is_immutable') || $transaction->isDirty('amount') || $transaction->isDirty('transaction_type')) {
                    throw new DomainException('Cannot modify an immutable transaction locked by night audit.');
                }
            }
        });

        static::deleting(function (FolioTransaction $transaction) {
            if ($transaction->getOriginal('is_immutable')) {
                throw new DomainException('Cannot delete an immutable transaction locked by night audit.');
            }
        });

        static::saved(function (FolioTransaction $transaction) {
            $transaction->folio?->recalculateTotals();
        });

        static::deleted(function (FolioTransaction $transaction) {
            $transaction->folio?->recalculateTotals();
        });
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
