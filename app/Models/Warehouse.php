<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = ['code', 'name', 'country'];

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }
}
