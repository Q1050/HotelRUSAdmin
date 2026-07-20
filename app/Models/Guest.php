<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Guest extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Concerns\BelongsToHotel;

    protected static function booted():void{static::creating(function(Guest $guest){if(!$guest->hotel_id)$guest->hotel_id=app()->bound('currentHotel')?app('currentHotel')->id:Hotel::query()->value('id');});}

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'id_type',
        'id_number',
        'id_status',
        'notes',
        'password',
        'account_status',
        'email_verified_at',
        'merged_into_guest_id',
        'do_not_rent_at',
        'do_not_rent_reason',
        'hotel_id',
    ];
    protected $hidden=['password'];
    protected function casts():array{return['password'=>'hashed','email_verified_at'=>'datetime','do_not_rent_at'=>'datetime'];}

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }
    public function reservations(): HasMany { return $this->hasMany(Reservation::class); }
    public function devices(): HasMany { return $this->hasMany(GuestDevice::class); }
    public function serviceRequests(): HasMany { return $this->hasMany(GuestServiceRequest::class); }
    public function mobileNotifications(): HasMany { return $this->hasMany(MobileNotification::class); }
    public function notificationPreference() { return $this->hasOne(GuestNotificationPreference::class); }
    public function privacyRequests(): HasMany { return $this->hasMany(GuestPrivacyRequest::class); }
    public function preArrivalSubmissions(): HasMany { return $this->hasMany(PreArrivalSubmission::class); }
    public function hotel(){return $this->belongsTo(Hotel::class);}
}
