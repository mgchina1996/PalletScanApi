<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_code' => $this->location_code,
            'type' => $this->type,
            'code' => $this->code,
            'quantity' => $this->quantity,
            'image_path' => $this->image_path,
            'products' => $this->whenLoaded('products', fn (): array => $this->products->map(fn ($product): array => [
                'id' => $product->id,
                'tpin' => $product->tpin,
                'quantity' => $product->quantity,
            ])->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
