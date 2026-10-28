<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceImport extends Model
{
    protected $fillable = ['filename', 'checksum', 'rows_updated', 'rows_unchanged', 'errors'];

    protected $casts = ['errors' => 'array'];
}
