<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class Bin extends Model
{
    protected $table = 'Bin';

    protected $primaryKey = 'BinID';

    public $timestamps = false;

    protected $connection = 'portal';

    protected $fillable = [
        'BinNumber', 'LocationID', 'CartonID', 'IsDynamic', 'CreatedOn',
    ];
}
