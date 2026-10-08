<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'Item';

    protected $primaryKey = 'ItemID';

    const UPDATED_AT = 'UpdatedOn';

    const CREATED_AT = 'CreatedOn';

    const FULFILLED_BY_MERCHANT_US = 138;

    const FULFILLED_BY_TOOLOTS = 137;

    const FULFILLED_BY_MERCHANT_CN = 429243;

    const WARRANTY = [
        '_14_Days_Limited' => '313',
        '_1_year' => '141',
        '_2_years' => '142',
        '_3_years' => '429242',
        '_4_years' => '429241',
        '_5_year' => '883',
        '_6_months' => '140',
        'No_Warranty' => '412208',
    ];

    const ITEM_TYPE_SIMPLE = 'simple';

    const ITEM_TYPE_BUNDLE = 'bundle';

    protected $connection = 'portal';
}
