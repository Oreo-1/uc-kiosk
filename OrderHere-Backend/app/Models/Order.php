<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    // ✅ Nama tabel yang benar
    protected $table = 'order';

    // ✅ Semua field yang boleh di-mass-assign
    protected $fillable = [
        'vendor_id',
        'dining_type',
        'payment_method',
        'notes_order',
        'status',
        'queue_number',
        'total_price',
        'total_estimated',
        'block_hash',
    ];

    // Relasi ke vendor
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    // Relasi ke food (many-to-many via order_food)
public function foods()
{
    return $this->belongsToMany(Food::class, 'order_food', 'order_id', 'food_id')
        ->withPivot('quantity', 'total_price', 'notes', 'parent_food_id');
}
}