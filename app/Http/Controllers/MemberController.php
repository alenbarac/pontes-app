<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MembershipPlan;
use App\Models\Workshop;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10); // Default to 10 if not provided
        $page = $request->input('page', 1);
        $filter = $request->input('filter', '');

        $query = Member::with([
            'workshops.memberships', // ✅ Memberships are fetched through workshops
            'workshopGroups.group',
        ]);


        if (!empty($filter)) {
            $query->where(function ($q) use ($filter) {
                $q->where('first_name', 'like', "%{$filter}%")
                    ->orWhere('last_name', 'like', "%{$filter}%")
                    ->orWhere('email', 'like', "%{$filter}%")
                    ->orWhere('dob', 'like', "%{$filter}%")
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
        $groups = MemberGroup::select('id', 'name')->get();
        $workshops = Workshop::select('id', 'name')->get();
        $membershipPlans = MembershipPlan::with(['workshop', 'member'])
        ->whereNotNull('member_id') // Ensure only memberships linked to a member are fetched
        ->whereNotNull('plan')
        ->where('plan', '!=', '')
        ->select('id', 'workshop_id', 'plan', 'total_fee', 'member_id')
        ->get();

        return inertia('Members/Create', [
            'groups' => $groups,
            'workshops' => $workshops,
            'membershipPlans' => $membershipPlans,
        ]);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMemberRequest $request)
    {
        $member = Member::create($request->validated());
        return redirect()->route('members.index')->with('success', 'Member created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Member $member)
    {
        return inertia('Members/Show', [
            'member' => new MemberResource($member),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMemberRequest $request, Member $member)
    {
        $member->update($request->validated());
        return redirect()->route('members.index')->with('success', 'Member updated successfully.');
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
