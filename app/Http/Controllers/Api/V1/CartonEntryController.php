<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartonEntriesRequest;
use App\Http\Resources\EntryResource;
use App\Models\Entry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CartonEntryController extends Controller
{
    public function store(StoreCartonEntriesRequest $request): JsonResponse
    {
        /** @var array{locationCode: string, cartonNumber: int|string, products: list<array{tpin: string, quantity: int}>} $data */
        $data = $request->validated();

        $entry = DB::transaction(function () use ($data): Entry {
            $entry = Entry::create([
                'location_code' => $data['locationCode'],
                'type' => Entry::TYPE_CARTON,
                'code' => (string) $data['cartonNumber'],
                'quantity' => null,
            ]);

            $entry->products()->createMany($data['products']);

            return $entry->load('products');
        });

        return (new EntryResource($entry))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
