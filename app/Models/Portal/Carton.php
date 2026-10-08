<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class Carton extends Model
{
    protected $table = 'Carton';

    protected $primaryKey = 'CartonID';

    public $timestamps = false;

    protected $connection = 'portal';

    protected $fillable = [
        'CartonNumber', 'Position', 'IsConfirmed', 'WarehouseID', 'ConfirmedBy', 'ConfirmedOn',
    ];

    public function bin() {}
}
