<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFood extends Model
{
    protected $table = 'order_food';
    public $timestamps = false;
    public $incrementing = false; // No auto-incrementing ID
    // Laravel doesn't natively support composite keys. 
    // Use Order::foods() or Food::orders() pivot methods instead.

    protected $fillable = ['food_id', 'order_id', 'quantity', 'total_price', 'notes', 'parent_food_id'];

    protected $casts = [
        'quantity' => 'integer',
        'total_price' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class, 'food_id', 'id');
    }

    /**
     * The parent food item this addon order-line belongs to.
     */
    public function parentFood(): BelongsTo
    {
        return $this->belongsTo(Food::class, 'parent_food_id', 'id');
    }
}