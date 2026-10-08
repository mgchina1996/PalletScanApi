<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->resource,
            'section' => $this->extractSection($this->resource),
        ];
    }

    private function extractSection(string $code): string
    {
        if (preg_match('/^[SN]\d+/', $code, $matches)) {
            return $matches[0];
        }

        return substr($code, 0, 2);
    }
}
