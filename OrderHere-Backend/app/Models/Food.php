<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    use HasFactory;

    protected $table = 'food';

    // ✅ HANYA field yang ADA di database (tanpa taste fields)
    protected $fillable = [
        'vendor_id',
        'name',
        'type',
        'price',
        'description',
        'image',
        'estimated_time',
        'flavor_attribute',
        'active',
    ];

    // ✅ Cast untuk boolean & numeric
    protected $casts = [
        'active' => 'boolean',
        'price' => 'decimal:2',
        'estimated_time' => 'integer',
    ];

    // ═══════════════════════════════════════
    // RELATIONS
    // ═══════════════════════════════════════

    /**
     * Relasi ke Vendor (many-to-one)
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Relasi ke Order (many-to-many via order_food pivot)
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_food', 'food_id', 'order_id')
            ->withPivot('quantity', 'total_price', 'notes', 'parent_food_id');
    }

    /**
     * Relasi ke FoodAddon (one-to-many)
     * ✅ TAMBAH: Dipanggil oleh FoodController (addons.addon)
     */
    public function addons()
    {
        return $this->hasMany(FoodAddon::class, 'food_id', 'id');
    }
}