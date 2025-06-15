<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Workshop;
use App\Models\MemberGroupWorkshop;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MemberWorkshopController extends Controller
{
    public function update(Request $request, Member $member, Workshop $workshop)
    {
        $data = $request->validate([
            'workshop_id'         => 'required|exists:workshops,id',
            'group_id'            => 'required|exists:member_groups,id',
            'membership_plan_id'  => 'required|exists:membership_plans,id',
            'start_date'          => 'required|date',
        ]);

        // 1. Update the pivot table for the membership info
        $member->workshops()
            ->updateExistingPivot($workshop->id, [
                'membership_plan_id'      => $data['membership_plan_id'],
                'membership_start_date'   => $data['start_date'],
            ]);

        // 2. Update (or create) the group assignment record
        MemberGroupWorkshop::updateOrCreate(
            [
                'member_id'   => $member->id,
                'workshop_id' => $workshop->id,
            ],
            [
                'member_group_id' => $data['group_id'],
            ]
        );

        // 3. Redirect back to the member’s show page (Inertia will pull fresh data)
        return redirect()
            ->route('members.show', $member)
            ->with('success', 'Workshop enrollment updated.');
    }
}
