<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = ['idempotency_key', 'from_warehouse_id', 'to_warehouse_id', 'product_id', 'quantity'];
}
