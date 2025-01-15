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
            'groups' => $this->groups->map(fn($group) => [
                'id' => $group->id,
                'name' => $group->name,
            ]),
            'workshops' => $this->workshops->map(fn($workshop) => [
                'id' => $workshop->id,
                'name' => $workshop->name,
            ]),
            // Map all memberships and include plan and associated workshop name
            'memberships' => $this->memberships->map(fn($membership) => [
                'plan' => $membership->plan,
                'workshop' => $membership->workshop->name ?? null, // Include workshop name
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

