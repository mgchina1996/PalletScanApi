<?php

namespace App\Models;

use Database\Factories\EntryProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntryProduct extends Model
{
    /** @use HasFactory<EntryProductFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tpin',
        'quantity',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
