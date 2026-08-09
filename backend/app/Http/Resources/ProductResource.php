<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user()?->hasAnyRole(['super-admin', 'admin', 'finance']) ?? false;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'brand' => $this->brand,
            'type' => $this->type,
            'price' => (float) $this->sell_price,
            'admin_fee' => (float) $this->admin_fee,
            'description' => $this->description,
            'is_available' => (bool) $this->is_available,
            'is_featured' => (bool) $this->is_featured,
            'category' => new CategoryResource($this->whenLoaded('category')),
            // Harga modal & margin hanya untuk admin — tidak pernah bocor ke app user.
            $this->mergeWhen($isAdmin, fn () => [
                'base_price' => (float) $this->base_price,
                'margin_type' => $this->margin_type,
                'margin_value' => (float) $this->margin_value,
                'profit' => $this->profit(),
                'provider' => new ProviderResource($this->whenLoaded('provider')),
                'is_active' => (bool) $this->is_active,
            ]),
        ];
    }
}
