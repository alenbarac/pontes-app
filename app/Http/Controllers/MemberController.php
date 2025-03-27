<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberGroupWorkshop;
use App\Models\MembershipPlan;
use App\Models\MemberWorkshop;
use App\Models\Workshop;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $filter = $request->input('filter', '');

        $query = Member::with([
            'workshops.memberships',
            'workshopGroups.group',
        ])->orderBy('created_at', 'desc');


        if (!empty($filter)) {
            $query->where(function ($q) use ($filter) {
                $q->where('first_name', 'like', "%{$filter}%")
                    ->orWhere('last_name', 'like', "%{$filter}%")
                    ->orWhere('email', 'like', "%{$filter}%")
                    ->orWhere('date_of_birth', 'like', "%{$filter}%")
                    ->orWhereHas('workshops', function ($q) use ($filter) {
                        $q->where('name', 'like', "%{$filter}%");
                    })
                    ->orWhereHas('memberGroups.memberGroup', function ($q) use ($filter) {
                        $q->where('name', 'like', "%{$filter}%");
                    })
                    ->orWhereHas('memberWorkshops.membershipPlan', function ($q) use ($filter) {
                        $q->where('plan', 'like', "%{$filter}%");
                    });
            });
        }

        $members = $query->paginate($perPage, ['*'], 'page', $page);

        return inertia('Members/Index', [
            'members' => [
                'data' => MemberResource::collection($members->items()),
                'pagination' => [
                    'current_page' => $members->currentPage(),
                    'last_page' => $members->lastPage(),
                    'per_page' => $members->perPage(),
                    'total' => $members->total(),
                ],
            ],
            'filters' => $request->only(['filter', 'per_page']),
        ]);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $workshops = Workshop::select('id', 'name')->get();
        $groups = MemberGroup::join('workshop_groups', 'member_groups.id', '=', 'workshop_groups.member_group_id')
            ->select('member_groups.id', 'member_groups.name', 'workshop_groups.workshop_id')
            ->get()
            ->groupBy('workshop_id');

        
        $membershipPlans = MembershipPlan::select('id', 'workshop_id', 'plan', 'total_fee')->get()->groupBy('workshop_id');

        return inertia('Members/Create', [
            'workshops' => $workshops,
            'groups' => $groups,
            'membershipPlans' => $membershipPlans,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMemberRequest $request)
    {
        $member = Member::create($request->validated());

        // Attach workshop only if it's not already attached
        if ($request->workshop_id && !$member->workshops()->where('workshop_id', $request->workshop_id)->exists()) {
            $member->workshops()->attach($request->workshop_id);
        }

        // Ensure the member isn't already assigned to the group
        if ($request->group_id && !MemberGroupWorkshop::where([
            'member_id' => $member->id,
            'workshop_id' => $request->workshop_id,
            'member_group_id' => $request->group_id,
        ])->exists()) {
            MemberGroupWorkshop::create([
                'member_id' => $member->id,
                'workshop_id' => $request->workshop_id,
                'member_group_id' => $request->group_id,
            ]);
        }
        // Ensure the membership plan isn't duplicated
        if ($request->membership_plan_id && !MemberWorkshop::where([
            'member_id' => $member->id,
            'workshop_id' => $request->workshop_id,
        ])->exists()) {
            MemberWorkshop::create([
                'member_id' => $member->id,
                'workshop_id' => $request->workshop_id,
                'membership_plan_id' => $request->membership_plan_id,
            ]);
        }

        return redirect()->route('members.index')->with('success', 'Član uspješno dodan.');
    }



    /**
     * Display the specified resource.
     */
    public function show(Member $member)
    {
        // Load member with related data
        $member->load([
            'workshops.memberships',
            'workshopGroups.group',
        ]);

        // Fetch all workshops for selection
        $workshops = Workshop::select('id', 'name')->get();

        // Fetch all groups linked to workshops
        $groups = MemberGroup::join('workshop_groups', 'member_groups.id', '=', 'workshop_groups.member_group_id')
            ->select('member_groups.id', 'member_groups.name', 'workshop_groups.workshop_id')
            ->get()
            ->groupBy('workshop_id');

        // Fetch all membership plans grouped by workshop
        $membershipPlans = MembershipPlan::select('id', 'workshop_id', 'plan', 'total_fee')->get()->groupBy('workshop_id');

        return inertia('Members/Edit', [
            'member' => new MemberResource($member),
            'workshops' => $workshops,
            'groups' => $groups,
            'membershipPlans' => $membershipPlans,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMemberRequest $request, Member $member)
    {
        // ✅ Update basic details
        $member->update($request->validated());

        // ✅ If a new workshop is selected, attach it
        if ($request->workshop_id && !$member->workshops()->where('workshop_id', $request->workshop_id)->exists()) {
            $member->workshops()->attach($request->workshop_id);
        }

        // ✅ Ensure the new group is not duplicated
        if ($request->group_id && !MemberGroupWorkshop::where([
            'member_id' => $member->id,
            'workshop_id' => $request->workshop_id,
            'member_group_id' => $request->group_id,
        ])->exists()) {
            MemberGroupWorkshop::create([
                'member_id' => $member->id,
                'workshop_id' => $request->workshop_id,
                'member_group_id' => $request->group_id,
            ]);
        }

        // ✅ Ensure the membership plan is unique
        if ($request->membership_plan_id && !MemberWorkshop::where([
            'member_id' => $member->id,
            'workshop_id' => $request->workshop_id,
        ])->exists()) {
            MemberWorkshop::create([
                'member_id' => $member->id,
                'workshop_id' => $request->workshop_id,
                'membership_plan_id' => $request->membership_plan_id,
            ]);
        }

        return redirect()->route('members.index')->with('success', 'Član uspješno ažuriran.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Member $member)
    {
        $member->delete();
        return redirect()->route('members.index')->with('success', 'Member deleted successfully.');
    }
}
