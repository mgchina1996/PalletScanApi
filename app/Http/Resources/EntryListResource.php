<?php

namespace App\Http\Resources;

use App\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryListResource extends JsonResource
{
    public const TIMEZONE = 'America/Los_Angeles';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $createdAt = $this->created_at?->copy()->setTimezone(self::TIMEZONE);

        return [
            'id' => (int) $this->id,
            'type' => (string) $this->type,
            'code' => (string) $this->code,
            'locationCode' => (string) $this->location_code,
            'quantity' => $this->type === Entry::TYPE_CARTON
                ? null
                : (int) $this->quantity,
            'productCount' => $this->type === Entry::TYPE_CARTON
                ? (int) $this->products_count
                : null,
            'createdAt' => $createdAt?->toIso8601String(),
            'time' => $createdAt?->format('H:i'),
        ];
    }
}
