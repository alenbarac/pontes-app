<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkshopResource extends JsonResource
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
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description ?? '',
            'groups_count' => $this->groups_count ?? ($this->whenLoaded('workshopGroups', fn() => $this->workshopGroups->count()) ?? 0),
            'members_count' => $this->members_count ?? ($this->whenLoaded('members', fn() => $this->members->count()) ?? 0),
            'created_at' => $this->created_at->toDateString(),
            'updated_at' => $this->updated_at->toDateString(),
            'groups' => $this->whenLoaded('groups', function () {
                return $this->groups->map(function ($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                    ];
                });
            }) ?? [],
        ];
    }
}
