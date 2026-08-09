<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
            'status' => $this->status,
            'referral_code' => $this->referral_code,
            'email_verified' => $this->email_verified_at !== null,
            'has_pin' => filled($this->pin_hash),
            'balance' => (float) ($this->whenLoaded('wallet')?->balance ?? $this->wallet?->balance ?? 0),
            'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
