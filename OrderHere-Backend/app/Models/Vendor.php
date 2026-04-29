<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Vendor extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'vendor';
    protected $primaryKey = 'id';
    public $timestamps = false; // Sesuai schema SQL Anda

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'phone_number',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        // Tambahkan jika ada field yang perlu casting
    ];

    // ================= RELATIONS =================
    public function foods(): HasMany
    {
        return $this->hasMany(Food::class, 'vendor_id', 'id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'vendor_id', 'id');
    }
}