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
use Illuminate\Http\RedirectResponse;

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
        $workshopId = $request->input('workshop_id', '');
        $groupId = $request->input('group_id', '');

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
                    ->orWhereHas('workshopGroups.group', function ($q) use ($filter) {
                        $q->where('name', 'like', "%{$filter}%");
                    });
                   
            });
        }

        // Filter by workshop
        if (!empty($workshopId)) {
            $query->whereHas('workshops', function ($q) use ($workshopId) {
                $q->where('workshops.id', $workshopId);
            });
        }

        // Filter by group (optionally scoped by workshop)
        if (!empty($groupId)) {
            $query->whereHas('workshopGroups', function ($q) use ($groupId, $workshopId) {
                $q->where('member_group_id', $groupId);
                if ($workshopId) {
                    $q->where('workshop_id', $workshopId);
                }
            });
        }

        $members = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

        // Get all workshops for the filter dropdown
        $workshops = Workshop::select('id', 'name')->orderBy('name')->get();

        // Groups for filter (optionally scoped by workshop)
        $groups = MemberGroup::query()
            ->select('member_groups.id', 'member_groups.name')
            ->when($workshopId, function ($q) use ($workshopId) {
                $q->join('workshop_groups', 'workshop_groups.member_group_id', '=', 'member_groups.id')
                  ->where('workshop_groups.workshop_id', $workshopId)
                  ->distinct();
            })
            ->orderBy('member_groups.name')
            ->get();

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
            'filter' => $filter,
            'workshopId' => $workshopId,
            'groupId' => $groupId,
            'workshops' => $workshops,
            'groups' => $groups,
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

    // Attach workshop and include membership_plan_id on the pivot.
    if ($request->workshop_id && !$member->workshops()->where('workshop_id', $request->workshop_id)->exists()) {
        $member->workshops()->attach($request->workshop_id, [
            'membership_plan_id' => $request->membership_plan_id,
            'membership_start_date' => $request->membership_start_date,
        ]);
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
            'invoices.workshop',
            'invoices.membershipPlan',
            'documents.documentTemplate',
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

        // Group invoices by workshop_id and convert to array format for Inertia
        $invoicesByWorkshop = $member->invoices->groupBy('workshop_id')->map(function ($invoices, $workshopId) {
            return $invoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'reference_code' => $invoice->reference_code,
                    'amount_due' => (float) $invoice->amount_due,
                    'amount_paid' => (float) $invoice->amount_paid,
                    'due_date' => $invoice->due_date,
                    'payment_status' => $invoice->payment_status,
                    'notes' => $invoice->notes,
                    'workshop_id' => $invoice->workshop_id,
                    'workshop' => $invoice->workshop ? [
                        'id' => $invoice->workshop->id,
                        'name' => $invoice->workshop->name,
                    ] : null,
                    'membership_plan' => $invoice->membershipPlan ? [
                        'id' => $invoice->membershipPlan->id,
                        'plan' => $invoice->membershipPlan->plan,
                        'total_fee' => $invoice->membershipPlan->total_fee,
                    ] : null,
                ];
            })->values()->all();
        })->toArray();

        // Format documents for Inertia
        $documents = $member->documents->map(function ($doc) {
            return [
                'id' => $doc->id,
                'template_id' => $doc->document_template_id,
                'document_type' => $doc->document_type,
                'template_name' => $doc->documentTemplate->name ?? '',
                'created_at' => $doc->created_at->format('d.m.Y H:i'),
                'additional_data' => $doc->additional_data,
            ];
        })->sortByDesc('created_at')->values();

        return inertia('Members/Show', [
            'member' => new MemberResource($member),
            'workshops' => $workshops,
            'groups' => $groups,
            'membershipPlans' => $membershipPlans,
            'invoicesByWorkshop' => $invoicesByWorkshop,
            'documents' => $documents,
        ]);
    }

     /**
     * Update basic member details.
     */

    public function update(UpdateMemberRequest $request, Member $member)
    {
        $member->update($request->validated());
        return redirect()->route('members.show', $member->id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Member $member): RedirectResponse
    {
        // Detach all workshop pivots
        $member->workshops()->detach();

        // Delete all group assignments
        MemberGroupWorkshop::where('member_id', $member->id)->delete();
        // TODO: If you have other related models, delete them as well
        // $member->invoices()->delete();
        // $member->memberships()->delete();

        // Finally delete the member record
        $member->delete();

        return redirect()->route('members.index')->with('success', 'Član i svi povezani podaci su obrisani.');
    }
}
