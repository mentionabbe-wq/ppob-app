<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Provider */
class ProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'is_active' => (bool) $this->is_active,
            'balance' => (float) $this->balance,
            'balance_synced_at' => $this->balance_synced_at?->toIso8601String(),
            'products_synced_at' => $this->products_synced_at?->toIso8601String(),
        ];
    }
}
