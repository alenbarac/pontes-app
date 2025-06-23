<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Workshop;
use App\Models\MemberGroupWorkshop;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MemberWorkshopController extends Controller
{
    public function store(Request $request, Member $member)
    {
        $data = $request->validate([
        'workshop_id'            => 'required|exists:workshops,id',
        'group_id'               => 'required|exists:member_groups,id',
        'membership_plan_id'     => 'required|exists:membership_plans,id',
        'membership_start_date'  => 'required|date',
        ]);

        // 1) attach the pivot
        $member->workshops()->attach($data['workshop_id'], [
        'membership_plan_id'    => $data['membership_plan_id'],
        'membership_start_date' => $data['membership_start_date'],
        ]);

        // 2) create group record
        MemberGroupWorkshop::create([
        'member_id'       => $member->id,
        'workshop_id'     => $data['workshop_id'],
        'member_group_id' => $data['group_id'],
        ]);

        return redirect()->back()->with('success','Radionica dodana');
    }

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

    public function destroy(Member $member, Workshop $workshop): RedirectResponse
    {
        // 1) detach only that one pivot
        $member->workshops()->detach($workshop->id);

        // 2) delete only that one group assignment
        MemberGroupWorkshop::where([
            ['member_id',   $member->id],
            ['workshop_id', $workshop->id],
        ])->delete();

        return redirect()
            ->route('members.show', $member)
            ->with('success', 'Upis iz radionice je uklonjen.');
    }

    public function destroyAll(Member $member): RedirectResponse
    {
        // 1) detach all pivot rows (member_workshop)
        $member->workshops()->detach();
        // 2) delete all group assignments
        MemberGroupWorkshop::where('member_id', $member->id)->delete();

        return redirect()
            ->route('members.show', $member)
            ->with('success', 'Svi upisi su uklonjeni.');
    }
}
