<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Concerns\BelongsToHotel, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'firstName',
        'lastName',
        'formality',
        'role',
        'staff_role_id',
        'status',
        'is_platform_admin',
        'email',
        'email_verified_at',
        'password',
        'two_factor_enabled',
        'hotel_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (! $user->name) {
                $user->name = trim(($user->firstName ?? '').' '.($user->lastName ?? ''));
            }
        });
        static::creating(function (User $user) {
            if (! $user->hotel_id) {
                $user->hotel_id = app()->bound('currentHotel') ? app('currentHotel')->id : Hotel::query()->value('id');
            }
        });
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function staffRole()
    {
        return $this->belongsTo(StaffRole::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'is_platform_admin' => 'boolean',
            'two_factor_code_expires_at' => 'datetime',
        ];
    }

    public function hasPermission(string $permission): bool
    {
        $granted = $this->staffRole?->permissions ?? \App\Support\StaffPermissions::TEMPLATES[$this->role] ?? [];

        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }
}
