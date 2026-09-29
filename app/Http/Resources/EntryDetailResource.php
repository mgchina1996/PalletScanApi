<?php

namespace App\Http\Resources;

use App\Models\Entry;
use App\Models\EntryProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $createdAt = $this->created_at?->copy()->setTimezone(EntryListResource::TIMEZONE);
        $updatedAt = $this->updated_at?->copy()->setTimezone(EntryListResource::TIMEZONE);
        $isCarton = $this->type === Entry::TYPE_CARTON;

        return [
            'id' => (int) $this->id,
            'type' => (string) $this->type,
            'code' => (string) $this->code,
            'locationCode' => (string) $this->location_code,
            'quantity' => $isCarton ? null : (int) $this->quantity,
            'productCount' => $isCarton ? $this->products->count() : null,
            'products' => $isCarton
                ? $this->products->map(static fn (EntryProduct $product): array => [
                    'id' => (int) $product->id,
                    'tpin' => (string) $product->tpin,
                    'quantity' => (int) $product->quantity,
                ])->all()
                : [],
            'imagePath' => $this->image_path,
            'createdAt' => $createdAt?->toIso8601String(),
            'updatedAt' => $updatedAt?->toIso8601String(),
            'time' => $createdAt?->format('H:i'),
        ];
    }
}
