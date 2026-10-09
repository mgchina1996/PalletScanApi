<?php

namespace App\Models;

use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory;

    public const TYPE_CARTON = 'carton';

    public const TYPE_TPIN = 'tpin';

    public const TYPE_SKU = 'sku';

    protected $fillable = [
        'location_code',
        'type',
        'code',
        'quantity',
        'image_path',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(EntryProduct::class);
    }

    public function stockGenerationLogs(): HasMany
    {
        return $this->hasMany(StockGenerationLog::class);
    }
}
