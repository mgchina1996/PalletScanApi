<?php

namespace App\Http\Resources;

use App\Models\EntryCodeChangeLog;
use App\Models\EntryProduct;
use App\Services\AdminEntryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminEntryResource extends JsonResource
{
    /** @var array<string, array{qtyOnHand: float, qtyAvailable: float}> */
    public array $inventory = [];

    public ?string $inventoryError = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location' => $this->location_code,
            'type' => $this->type,
            'code' => $this->code,
            'quantity' => $this->quantity,
            'cartonId' => $this->carton_id,
            'status' => app(AdminEntryService::class)->status($this->resource),
            'error' => $this->error,
            'createdAt' => $this->created_at?->setTimezone(EntryListResource::TIMEZONE)->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updated_at?->setTimezone(EntryListResource::TIMEZONE)->format('Y-m-d H:i:s'),
            'imageUrl' => $this->image_path ? route('admin.image', ['entry' => $this->id]) : null,
            'inventoryError' => $this->inventoryError,
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn (EntryProduct $product): array => [
                'id' => $product->id,
                'tpin' => $product->tpin,
                'quantity' => $product->quantity,
                'qtyOnHand' => $this->inventory[$product->tpin]['qtyOnHand'] ?? null,
                'qtyAvailable' => $this->inventory[$product->tpin]['qtyAvailable'] ?? null,
            ])),
            'logs' => $this->whenLoaded('stockGenerationLogs'),
            'codeChanges' => $this->whenLoaded('codeChangeLogs', fn () => $this->codeChangeLogs->map(static fn (EntryCodeChangeLog $log): array => [
                'id' => $log->id,
                'oldCode' => $log->old_code,
                'newCode' => $log->new_code,
                'actor' => $log->actor,
                'createdAt' => $log->created_at?->setTimezone(EntryListResource::TIMEZONE)->format('Y-m-d H:i:s'),
            ])),
        ];
    }
}
