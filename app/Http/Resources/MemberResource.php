<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'first_name'     => $this->first_name ?? '',
            'last_name'      => $this->last_name ?? '',
            'date_of_birth'  => $this->date_of_birth,
            'phone_number'   => $this->phone_number ?? '',
            'email'          => $this->email ?? '',
            'is_active'      => $this->is_active,
            'parent_contact' => $this->parent_contact,
            'parent_email'   => $this->parent_email,
            'invoice_email'   => $this->invoice_email,

            // Include the workshopGroups with the pivot field (member_group_id)
            'workshopGroups' => $this->whenLoaded('workshopGroups', function () {
                return $this->workshopGroups->map(fn($wGroup) => [
                    'id'              => $wGroup->id,
                    'workshop_id'     => $wGroup->workshop_id, 
                    'member_group_id' => $wGroup->member_group_id, // from pivot table
                    'group'           => [
                        'id'   => $wGroup->group->id ?? null,
                        'name' => $wGroup->group->name ?? '',
                    ],
                ]);
            }),

            // Include workshops and add the pivot data for membership_plan_id
            'workshops' => $this->whenLoaded('workshops', function () {
                return $this->workshops->map(function ($workshop) {
                    // Find the membership plan chosen by the member from the workshop's memberships
                    $chosenPlan = $workshop->memberships->firstWhere('id', $workshop->pivot->membership_plan_id);
                    return [
                        'id'                 => $workshop->id,
                        'name'               => $workshop->name,
                        // Return the pivot data
                        'membership_plan_id' => $workshop->pivot->membership_plan_id ?? null,
                        // Return full details of the selected membership plan if found
                        'membership_plan'    => $chosenPlan ? [
                            'id'        => $chosenPlan->id,
                            'plan'      => $chosenPlan->plan,
                            'total_fee' => $chosenPlan->total_fee,
                            'start_date' => $workshop->pivot->membership_start_date,
                            'end_date'   => $workshop->pivot->membership_end_date,
                        ] : null,
                        'memberships'        => $workshop->relationLoaded('memberships')
                            ? $workshop->memberships->map(fn($membership) => [
                                'id'        => $membership->id,
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
