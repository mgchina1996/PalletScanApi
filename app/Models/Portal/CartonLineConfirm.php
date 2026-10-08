<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class CartonLineConfirm extends Model
{
    protected $table = 'CartonLineConfirm';

    protected $primaryKey = 'CartonLineConfirmID';

    public $timestamps = false;

    protected $connection = 'portal';

    protected $fillable = [
        'CartonID', 'ItemID', 'Quantity',
    ];
}
