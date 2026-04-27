<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Order extends Model
{
    // 'order' is a SQL reserved word, but Laravel handles it safely via $table
    protected $table = 'order';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'vendor_id', 'status', 'queue_number', 'total_price', 'total_estimated'
    ];

    protected $casts = [
        'queue_number' => 'integer',
        'total_price' => 'decimal:2',
        'total_estimated' => 'integer',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'id');
    }

    public function foods(): BelongsToMany
    {
        return $this->belongsToMany(Food::class, 'order_food', 'order_id', 'food_id')
                    ->withPivot('quantity', 'total_price', 'notes');
    }
}