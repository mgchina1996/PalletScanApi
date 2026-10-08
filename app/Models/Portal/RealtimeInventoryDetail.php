<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RealtimeInventoryDetail extends Model
{
    use HasUuids;

    protected $table = 'portal_realtime_inventory_detail';

    protected $primaryKey = 'Id';

    protected $keyType = 'string';

    public $timestamps = false;

    protected $connection = 'portal';

    protected $fillable = [
        'ItemId', 'LocationId', 'BinId', 'QtyOnHand', 'QtyAvailable', 'CreationTime', 'LastModificationTime',
    ];
}
