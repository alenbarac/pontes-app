<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberGroupRequest;
use App\Http\Resources\MemberGroupResource;
use App\Models\MemberGroup;
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
    public function show(MemberGroup $memberGroup)
    {
        return inertia('MemberGroups/Show', [
            'group' => new MemberGroupResource($memberGroup->load('members')),
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
     * Remove the specified resource from storage.
     */
    public function destroy(MemberGroup $memberGroup)
    {
        $memberGroup->delete();

        return redirect()->route('member-groups.index')->with('success', 'Group deleted successfully.');
    }
}
