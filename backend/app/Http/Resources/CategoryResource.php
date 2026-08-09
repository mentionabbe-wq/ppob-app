<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'color' => $this->color,
            'type' => $this->type,
            'input_label' => $this->input_label,
            'input_type' => $this->input_type,
            'description' => $this->description,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
