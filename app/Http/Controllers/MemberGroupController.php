<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberGroupRequest;
use App\Http\Resources\MemberGroupResource;
use App\Http\Resources\MemberResource;
use App\Models\MemberGroup;
use App\Models\MemberGroupWorkshop;
use App\Models\Workshop;
use Illuminate\Http\Request;

class MemberGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
{
    $workshops = Workshop::select('id', 'name')->get();

    $groups = MemberGroup::withCount('members')
                ->with('assignedWorkshop')
                ->paginate(10);

    return inertia('MemberGroups/Index', [
        'groups' => MemberGroupResource::collection($groups),
        'workshops' => $workshops,
    ]);
}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $workshops = Workshop::select('id', 'name')->get();
        return inertia('MemberGroups/Create', [
            'workshops' => $workshops,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(MemberGroupRequest $request)
{
    // 1. Create the group
    $group = MemberGroup::create([
        'name' => $request->input('name'),
        'description' => $request->input('description'),
    ]);

    // 2. Associate with a workshop via pivot table
    \DB::table('workshop_groups')->insert([
        'workshop_id' => $request->input('workshop_id'),
        'member_group_id' => $group->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return redirect()
        ->route('member-groups.index')
        ->with('success', 'Grupa uspješno kreirana i povezana s radionicom.');
}


    /**
     * Display the specified resource.
     */
    public function show(Request $request, MemberGroup $memberGroup)
    {
        // Load the group with its assigned workshop
        $memberGroup->load('assignedWorkshop');

        // Get the workshop ID for this group
        $workshopId = $memberGroup->assignedWorkshop?->id;

        // Get members in this group for the specific workshop
        // Members are linked through member_workshop_group table
        $memberIdsQuery = \DB::table('member_workshop_group')
            ->where('member_group_id', $memberGroup->id)
            ->when($workshopId, function ($query) use ($workshopId) {
                $query->where('workshop_id', $workshopId);
            })
            ->pluck('member_id')
            ->unique();

        // Build the members query with search
        $membersQuery = \App\Models\Member::whereIn('id', $memberIdsQuery)
            ->with(['workshopGroups.group', 'workshops']);

        // Apply search filter if provided
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $membersQuery->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Paginate members
        $members = $membersQuery
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(20);

        // Calculate statistics
        $allMemberIds = \DB::table('member_workshop_group')
            ->where('member_group_id', $memberGroup->id)
            ->when($workshopId, function ($query) use ($workshopId) {
                $query->where('workshop_id', $workshopId);
            })
            ->pluck('member_id')
            ->unique();

        $allMembers = \App\Models\Member::whereIn('id', $allMemberIds)->get();
        
        $membersWithDob = $allMembers->whereNotNull('date_of_birth');
        $averageAge = $membersWithDob->isNotEmpty() 
            ? $membersWithDob->avg(fn($m) => now()->diffInYears($m->date_of_birth))
            : null;

        $stats = [
            'total_members' => $allMembers->count(),
            'active_members' => $allMembers->where('is_active', true)->count(),
            'average_age' => $averageAge ? round($averageAge, 1) : null,
        ];

        // Get other groups in the same workshop for bulk reassignment
        $otherGroups = [];
        if ($workshopId) {
            $otherGroups = MemberGroup::join('workshop_groups', 'member_groups.id', '=', 'workshop_groups.member_group_id')
                ->where('workshop_groups.workshop_id', $workshopId)
                ->where('member_groups.id', '!=', $memberGroup->id)
                ->select('member_groups.id', 'member_groups.name')
                ->orderBy('member_groups.name')
                ->get();
        }

        return inertia('MemberGroups/Show', [
            'group' => new MemberGroupResource($memberGroup),
            'members' => MemberResource::collection($members),
            'membersMeta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
            'statistics' => $stats,
            'otherGroups' => $otherGroups,
            'search' => $request->search ?? '',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MemberGroupRequest $request, MemberGroup $memberGroup)
    {
        $memberGroup->update($request->validated());

        return redirect()->route('member-groups.index')->with('success', 'Group updated successfully.');
    }

    /**
     * Bulk reassign members to another group.
     */
    public function bulkReassign(Request $request, MemberGroup $memberGroup)
    {
        $request->validate([
            'member_ids' => 'required|array',
            'member_ids.*' => 'required|exists:members,id',
            'target_group_id' => 'required|exists:member_groups,id',
            'workshop_id' => 'required|exists:workshops,id',
        ]);

        // Ensure target group is in the same workshop
        $targetGroupWorkshop = \DB::table('workshop_groups')
            ->where('member_group_id', $request->target_group_id)
            ->where('workshop_id', $request->workshop_id)
            ->exists();

        if (!$targetGroupWorkshop) {
            return back()->withErrors(['target_group_id' => 'Target group must be in the same workshop.']);
        }

        // Update member group assignments
        MemberGroupWorkshop::whereIn('member_id', $request->member_ids)
            ->where('workshop_id', $request->workshop_id)
            ->where('member_group_id', $memberGroup->id)
            ->update(['member_group_id' => $request->target_group_id]);

        return redirect()
            ->route('member-groups.show', $memberGroup)
            ->with('success', 'Members successfully reassigned to the new group.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MemberGroup $memberGroup)
    {
        $memberGroup->delete();

        return redirect()->route('member-groups.index')->with('success', 'Group deleted successfully.');
    }
}
