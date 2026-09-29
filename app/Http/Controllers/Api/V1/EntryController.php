<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListEntriesRequest;
use App\Http\Resources\EntryListResource;
use App\Models\Entry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EntryController extends Controller
{
    public function __invoke(ListEntriesRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $query = Entry::query()
            ->select([
                'id',
                'location_code',
                'type',
                'code',
                'quantity',
                'created_at',
            ])
            ->withCount('products');

        $type = $validated['type'] ?? 'all';

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $search = trim((string) ($validated['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('code', 'like', "%{$search}%")
                    ->orWhereHas('products', function (Builder $query) use ($search): void {
                        $query->where('tpin', 'like', "%{$search}%");
                    });
            });
        }

        $entries = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($validated['perPage'] ?? 20))
            ->withQueryString();

        return EntryListResource::collection($entries)->additional([
            'timezone' => EntryListResource::TIMEZONE,
        ]);
    }
}
