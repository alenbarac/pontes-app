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
            'date_of_birth' => $this->date_of_birth,
            'phone_number' => $this->phone_number ?? '',
            'email' => $this->email ?? '',
            'is_active' => $this->is_active,
            'parent_contact' => $this->parent_contact,
            'parent_email' => $this->parent_email,

            'groups' => $this->whenLoaded('workshopGroups', function () {
                return $this->workshopGroups->map(fn($group) => [
                    'id'   => $group->group->id ?? null,
                    'name' => $group->group->name ?? '',
                ]);
            }),

            // Map workshops and include memberships inside them.
            'workshops' => $this->whenLoaded('workshops', function () {
                return $this->workshops->map(function ($workshop) {
                    return [
                        'id'   => $workshop->id,
                        'name' => $workshop->name,
                        'memberships' => $workshop->relationLoaded('memberships')
                            ? $workshop->memberships->map(fn($membership) => [
                                'plan'      => $membership->plan,
                                'total_fee' => $membership->total_fee,
                            ])
                            : [],
                    ];
                });
            }),
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
