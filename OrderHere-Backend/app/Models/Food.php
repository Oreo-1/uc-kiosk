<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Food extends Model
{
    protected $table = 'food';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'vendor_id', 'name', 'type', 'price', 'description',
        'image', 'estimated_time', 'flavor_attribute', 'active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'estimated_time' => 'integer',
        'active' => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'id');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_food', 'food_id', 'order_id')
                    ->withPivot('quantity', 'total_price', 'notes');
    }
}