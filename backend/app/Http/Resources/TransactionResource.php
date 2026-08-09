<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Transaction */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Harga modal & keuntungan hanya ditampilkan kepada admin dan
        // akun reseller. User ritel tidak perlu (dan tidak boleh) tahu
        // margin kita terhadap provider.
        $showMargin = $request->user()?->hasAnyRole(['super-admin', 'admin', 'finance', 'reseller']) ?? false;

        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'ref_id' => $this->ref_id,
            'product_name' => $this->product_name,
            'customer_no' => $this->customer_no,
            'customer_name' => $this->customer_name,
            'sell_price' => (float) $this->sell_price,
            'admin_fee' => (float) $this->admin_fee,
            'discount' => (float) $this->discount,
            'total_paid' => (float) $this->total_paid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'serial_number' => $this->serial_number,
            'message' => $this->provider_message,
            'meta' => $this->meta,
            'invoice_url' => route('api.v1.transactions.invoice', $this->id),
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'product' => new ProductResource($this->whenLoaded('product')),
            'user' => new UserResource($this->whenLoaded('user')),
            $this->mergeWhen($showMargin, fn () => [
                'base_price' => (float) $this->base_price,
                'profit' => (float) $this->profit,
            ]),
        ];
    }
}
