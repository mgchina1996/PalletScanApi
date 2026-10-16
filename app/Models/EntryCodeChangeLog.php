<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryCodeChangeLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'entry_id',
        'old_code',
        'new_code',
        'actor',
    ];
}
