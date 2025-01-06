<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
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
            'first_name' => $this->first_name ?? '',
            'last_name' => $this->last_name ?? '',
            'birth_year' => $this->birth_year,
            'phone_number' => $this->phone_number ?? '',
            'email' => $this->email ?? '',
            'is_active' => $this->is_active,
            'parent_contact' => $this->parent_contact,
            'parent_email' => $this->parent_email,
            'membership_name' => $this->membership?->plan ?? 'No Membership',
            'group_names' => $this->groups->pluck('name')->toArray(),
            'workshop_names' => $this->workshops->pluck('name')->toArray(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
