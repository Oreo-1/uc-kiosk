<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodAddon extends Model
{
    protected $table = 'food_addons';
    public $timestamps = false;

    protected $fillable = ['food_id', 'addons_id', 'extra_price'];

    protected $casts = [
        'extra_price' => 'decimal:2',
    ];

    /**
     * The base food that owns this addon entry.
     */
    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class, 'food_id', 'id');
    }

    /**
     * The addon food item being referenced.
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Food::class, 'addons_id', 'id');
    }
}
