<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $table = 'vendor';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = ['name'];

    public function foods(): HasMany
    {
        return $this->hasMany(Food::class, 'vendor_id', 'id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'vendor_id', 'id');
    }
}