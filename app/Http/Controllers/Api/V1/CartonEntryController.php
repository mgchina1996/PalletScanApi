<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartonEntriesRequest;
use App\Http\Resources\EntryResource;
use App\Models\Entry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CartonEntryController extends Controller
{
    public function store(StoreCartonEntriesRequest $request, string $locationCode): JsonResponse
    {
        /** @var array<int, array{cartonID: int, tpin: string, quantity: int}> $items */
        $items = $request->validated();

        $entries = DB::transaction(function () use ($items, $locationCode): Collection {
            return collect($items)
                ->groupBy('cartonID')
                ->map(function (Collection $products, int|string $cartonId) use ($locationCode): Entry {
                    $entry = Entry::create([
                        'location_code' => $locationCode,
                        'type' => Entry::TYPE_CARTON,
                        'code' => (string) $cartonId,
                        'quantity' => null,
                    ]);

                    $entry->products()->createMany($products->map(fn (array $product): array => [
                        'tpin' => $product['tpin'],
                        'quantity' => $product['quantity'],
                    ])->all());

                    return $entry->load('products');
                })
                ->values();
        });

        return EntryResource::collection($entries)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
