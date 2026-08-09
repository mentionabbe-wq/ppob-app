<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\AppNotification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'data' => $this->data,
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
