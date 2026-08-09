<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Deposit */
class DepositResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'amount' => (float) $this->amount,
            'unique_code' => (int) $this->unique_code,
            'total_amount' => (float) $this->total_amount,
            'method' => $this->method,
            'channel' => $this->channel,
            'va_number' => $this->va_number,
            'qris_payload' => $this->qris_payload,
            'proof_url' => $this->proof_path ? Storage::disk('public')->url($this->proof_path) : null,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'reject_reason' => $this->reject_reason,
            'expired_at' => $this->expired_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
