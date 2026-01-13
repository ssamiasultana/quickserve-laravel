<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'worker_id' => $this->worker_id,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'service_address' => $this->service_address,
            'special_instructions' => $this->special_instructions,
            'service_id' => $this->service_id,
            'service_subcategory_id' => $this->service_subcategory_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal_amount' => $this->subtotal_amount,
            'shift_type' => $this->shift_type,
            'shift_charge_percent' => $this->shift_charge_percent,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'scheduled_at' => optional($this->scheduled_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'customer' => $this->whenLoaded('customer'),
            'worker' => $this->whenLoaded('worker'),
            'service' => $this->whenLoaded('service'),
            'service_category' => $this->whenLoaded('serviceCategory'),
            'service_subcategory' => $this->whenLoaded('serviceSubcategory'),
        ];
    }
}


