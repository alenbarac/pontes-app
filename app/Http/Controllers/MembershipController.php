<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMembershipRequest;
use App\Http\Requests\UpdateMembershipRequest;
use App\Http\Resources\MembershipResource;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $memberships = MembershipPlan::with('member')->paginate(10);

        return inertia('Memberships/Index', [
            'memberships' => MembershipResource::collection($memberships),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMembershipRequest $request)
    {
        MembershipPlan::create($request->validated());

        return redirect()->route('memberships.index')->with('success', 'Membership created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MembershipPlan $membership)
    {
        return inertia('Memberships/Show', [
            'membership' => new MembershipResource($membership->load('member')),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMembershipRequest $request, MembershipPlan $membership)
    {
        $membership->update($request->validated());

        return redirect()->route('memberships.index')->with('success', 'Membership updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MembershipPlan $membership)
    {
        $membership->delete();

        return redirect()->route('memberships.index')->with('success', 'Membership deleted successfully.');
    }
}
