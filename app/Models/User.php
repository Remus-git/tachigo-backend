<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
/**
 * @method \Laravel\Sanctum\NewAccessToken createToken(string $name, array $abilities = [])
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable,HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
    'email',
    'phone',
    'phone_verified_at',
    'password',
    'role',
    'is_active',
    'latitude',
    'longitude',
    'address',
    'gender',
    'date_of_birth',
    'profile_photo_url',
    'otp_code',
    'otp_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
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
            'phone_verified_at'  => 'datetime',
            'date_of_birth'      => 'date',
            'is_active'          => 'boolean',
        ];
    }
    public function restaurant()
{
    return $this->hasOne(Restaurant::class,'user_id');
}
    public function orders(){
        return $this->hasMany(Order::class,'user_id');
    }
    public function cart()
{
    return $this->hasOne(Cart::class);
}
public function notifications()
{
    return $this->hasMany(Notification::class);
}

public function rider() {
    return $this->hasOne(Rider::class);
}
}
