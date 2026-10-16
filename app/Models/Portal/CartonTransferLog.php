<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class CartonTransferLog extends Model
{
    protected $connection = 'portal';

    protected $table = 'CartonTransferLog';

    protected $primaryKey = 'CartonTransferLogID';

    public $timestamps = false;

    protected $fillable = [
        'BinID',
        'FromBinNumber',
        'CartonID',
        'LocationID',
        'ToBinNumber',
        'CreatedBy',
        'CreatedOn',
    ];
}
