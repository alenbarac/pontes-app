<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member' => [
                'id' => $this->member->id,
                'name' => $this->member->name,
            ],
            'plan' => $this->plan,
            'fee' => $this->fee,
            'billing_frequency' => $this->billing_frequency,
            'discount_type' => $this->discount_type,
            'total_fee' => $this->total_fee,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date ? $this->end_date->toDateString() : null,
            'status' => $this->status,
        ];
    }
}
