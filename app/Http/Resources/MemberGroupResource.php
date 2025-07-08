<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberGroupResource extends JsonResource
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
            'description' => $this->description ?? '',
            'members_count' => $this->members_count,
            'created_at' => $this->created_at->toDateString(),
            'updated_at' => $this->updated_at->toDateString(),
            'workshop' => $this->whenLoaded('assignedWorkshop', function () {
            return [
                'id' => $this->assignedWorkshop->id,
                'name' => $this->assignedWorkshop->name,
                ];
        }),
        ];
    }
}
