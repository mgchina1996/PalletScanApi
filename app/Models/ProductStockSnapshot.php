<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStockSnapshot extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'has_image' => 'boolean',
            'is_bundle_child' => 'boolean',
            'bundle_parent_visible' => 'boolean',
            'is_parts' => 'boolean',
            'price' => 'decimal:4',
            'special_price' => 'decimal:4',
            'qty_available' => 'decimal:4',
            'ss_date' => 'date',
        ];
    }
}
