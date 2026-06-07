<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    use HasFactory;

    // ✅ Nama tabel yang benar
    protected $table = 'food';

    protected $fillable = [
        'vendor_id',
        'name',
        'price',
        'estimated_time',
        'active',
        'Manis',
        'Pahit',
        'Asin',
        'Asam',
        'Pedas',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

        public function addons()
    {
        // Jika satu makanan memiliki banyak addon
        return $this->hasMany(FoodAddon::class, 'food_id');
    }
    
public function orders()
{
    return $this->belongsToMany(Order::class, 'order_food', 'food_id', 'order_id')
        ->withPivot('quantity', 'total_price', 'notes', 'parent_food_id');
}
}