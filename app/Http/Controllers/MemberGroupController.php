<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberGroupRequest;
use App\Http\Resources\MemberGroupResource;
use App\Models\MemberGroup;
use Illuminate\Http\Request;

class MemberGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $groups = MemberGroup::with('members')->paginate(10);

        return inertia('MemberGroups/Index', [
            'groups' => MemberGroupResource::collection($groups),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(MemberGroupRequest $request)
    {
        MemberGroup::create($request->validated());

        return redirect()->route('member-groups.index')->with('success', 'Group created successfully.');
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
