<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductEntriesRequest;
use App\Http\Resources\EntryResource;
use App\Models\Entry;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ProductEntryController extends Controller
{
    public function store(StoreProductEntriesRequest $request, string $type): JsonResponse
    {
        /** @var array{locationCode: string, tpin: string, quantity: int} $data */
        $data = $request->validated();
        $entry = Entry::create([
            'location_code' => $data['locationCode'],
            'type' => $type,
            'code' => $data['tpin'],
            'quantity' => $data['quantity'],
        ]);

        return (new EntryResource($entry))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
