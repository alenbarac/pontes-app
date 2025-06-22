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

    public function rollOut(Request $request, Member $member, Workshop $workshop)
    {
        $data = $request->validate([
            'membership_end_date' => 'nullable|date',
        ]);

        // Default to today's date if termination_date is not provided.
        $terminationDate = $data['membership_end_date'] ?? now()->format('Y-m-d');

        // Call the rollOutFromWorkshop method on the Member model.
        if ($member->rollOutFromWorkshop($workshop, $terminationDate)) {
            return redirect()
                ->route('members.show', $member)
                ->with('success', 'Workshop enrollment terminated.');
        }

        return redirect()
            ->route('members.show', $member)
            ->with('error', 'Termination failed.');
    }
}
