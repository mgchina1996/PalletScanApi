<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntryDetailResource;
use App\Http\Resources\EntryListResource;
use App\Models\Entry;

class EntryDetailController extends Controller
{
    public function __invoke(Entry $entry): EntryDetailResource
    {
        $entry->load('products');

        return (new EntryDetailResource($entry))->additional([
            'timezone' => EntryListResource::TIMEZONE,
        ]);
    }
}
