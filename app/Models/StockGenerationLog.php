<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockGenerationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'operation_id',
        'entry_id',
        'type',
        'code',
        'action',
        'carton_id',
        'bin_id',
        'item_id',
        'inventory_detail_id',
        'tpin',
        'location_code',
        'quantity',
        'qty_on_hand_before',
        'qty_on_hand_after',
        'qty_available_before',
        'qty_available_after',
    ];
}
