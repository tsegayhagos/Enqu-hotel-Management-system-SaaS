<?php

namespace App\Models;

use App\Support\Tenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /** is_super_admin is intentionally NOT mass-assignable. */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function hotels(): BelongsToMany
    {
        return $this->belongsToMany(Hotel::class)->withPivot('is_primary')->withTimestamps();
    }

    /** The hotel this user is currently operating in. */
    public function currentHotel(): ?Hotel
    {
        return Tenant::hotel();
    }

    /** Whether the user may operate inside the given hotel. */
    public function canAccessHotel(int $hotelId): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->hotels()
            ->whereKey($hotelId)
            ->where('hotels.is_active', true)
            ->exists();
    }
}
