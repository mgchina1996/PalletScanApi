<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class ItemExtend extends Model
{
    protected $connection = 'portal';

    protected $table = 'ItemExtend';

    protected $primaryKey = 'ItemId';

    public $timestamps = false;

    protected $fillable = [
        'IsSyncStockToBolton',
        'IsSyncStockToMagento',
    ];
}
