<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['sku', 'name', 'price_cents', 'low_stock_threshold'];

    protected $casts = ['price_cents' => 'integer', 'low_stock_threshold' => 'integer'];

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }
}
