<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    use HasFactory;

    protected $table = 'food';

    // ✅ TAMBAHKAN SEMUA FIELD YANG BOLEH DI-MASS-ASSIGN
    protected $fillable = [
        'vendor_id',
        'name',
        'type',              // ✅ TAMBAH
        'price',
        'description',       // ✅ TAMBAH
        'image',             // ✅ TAMBAH
        'estimated_time',
        'flavor_attribute',  // ✅ TAMBAH
        'active',
        'Manis',
        'Pahit',
        'Asin',
        'Asam',
        'Pedas',
    ];

    // ✅ Cast untuk boolean & numeric
    protected $casts = [
        'active' => 'boolean',
        'price' => 'decimal:2',
        'estimated_time' => 'integer',
        'Manis' => 'integer',
        'Pahit' => 'integer',
        'Asin' => 'integer',
        'Asam' => 'integer',
        'Pedas' => 'integer',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_food', 'food_id', 'order_id')
            ->withPivot('quantity', 'total_price', 'notes', 'parent_food_id');
    }

    public function addons()
    {
        return $this->hasMany(FoodAddon::class, 'food_id', 'id');
    }
}