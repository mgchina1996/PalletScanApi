<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductEntriesRequest;
use App\Http\Resources\EntryResource;
use App\Models\Entry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ProductEntryController extends Controller
{
    public function store(StoreProductEntriesRequest $request, string $locationCode, string $type): JsonResponse
    {
        /** @var array<int, array{tpin: string, quantity: int}> $items */
        $items = $request->validated();

        $entries = DB::transaction(fn (): Collection => collect($items)->map(
            fn (array $item): Entry => Entry::create([
                'location_code' => $locationCode,
                'type' => $type,
                'code' => $item['tpin'],
                'quantity' => $item['quantity'],
            ]),
        ));

        return EntryResource::collection($entries)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
