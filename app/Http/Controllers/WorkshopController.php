<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkshopRequest;
use App\Http\Requests\UpdateWorkshopRequest;
use App\Http\Resources\WorkshopResource;
use App\Models\MemberGroupWorkshop;
use App\Models\Workshop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkshopController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $workshops = Workshop::withCount(['workshopGroups as groups_count', 'members'])
            ->with(['workshopGroups.memberGroup:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Transform the data to include groups in the expected format
        $workshops->transform(function ($workshop) {
            $workshop->groups = $workshop->workshopGroups->map(function ($wg) {
                return $wg->memberGroup;
            })->filter();
            return $workshop;
        });

        return Inertia::render('Workshops/Index', [
            'workshops' => WorkshopResource::collection($workshops),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Workshops/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWorkshopRequest $request): RedirectResponse
    {
        Workshop::create($request->validated());

        return redirect()
            ->route('workshops.index')
            ->with('success', 'Radionica uspješno kreirana.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Workshop $workshop): Response
    {
        $workshop->loadCount(['groups', 'members'])->load('groups:id,name');

        return Inertia::render('Workshops/Show', [
            'workshop' => new WorkshopResource($workshop),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Workshop $workshop): Response
    {
        return Inertia::render('Workshops/Edit', [
            'workshop' => new WorkshopResource($workshop),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWorkshopRequest $request, Workshop $workshop): RedirectResponse
    {
        $workshop->update($request->validated());

        return redirect()
            ->route('workshops.index')
            ->with('success', 'Radionica uspješno ažurirana.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Workshop $workshop): RedirectResponse
    {
        // Delete all member-workshop-group assignments for this workshop
        MemberGroupWorkshop::where('workshop_id', $workshop->id)->delete();

        // Detach all member relationships (member_workshop pivot)
        // This will cascade delete due to foreign key constraint, but we'll be explicit
        $workshop->members()->detach();

        // Delete all membership plans (will cascade via foreign key, but being explicit)
        $workshop->memberships()->delete();

        // Delete workshop groups relationships (will cascade via foreign key)
        // The groups themselves are not deleted, only the relationship

        // Finally delete the workshop record
        $workshop->delete();

        return redirect()
            ->route('workshops.index')
            ->with('success', 'Radionica i svi povezani podaci su obrisani.');
    }
}
