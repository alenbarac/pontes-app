<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberGroupRequest;
use App\Http\Resources\MemberGroupResource;
use App\Http\Resources\MemberResource;
use App\Models\MemberGroup;
use App\Models\MemberGroupWorkshop;
use App\Models\Workshop;
use App\Models\Invoice;
use App\Http\Controllers\InvoiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Carbon\Carbon;

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
    DB::table('workshop_groups')->insert([
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
        $memberIdsQuery = DB::table('member_workshop_group')
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
        $allMemberIds = DB::table('member_workshop_group')
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
        $targetGroupWorkshop = DB::table('workshop_groups')
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
     * Bulk download payment slips for selected members.
     */
    public function bulkDownloadSlips(Request $request, MemberGroup $memberGroup)
    {
        try {
            $request->validate([
                'member_ids' => 'required|array',
                'member_ids.*' => 'required|exists:members,id',
                'month' => 'required|regex:/^\d{4}-\d{2}$/', // Format: YYYY-MM
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return JSON error for API requests (axios sends X-Requested-With header)
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
            return back()->withErrors($e->errors());
        }

        // Check if this is an AJAX/API request (axios sends X-Requested-With: XMLHttpRequest)
        $isAjaxRequest = $request->ajax() || $request->wantsJson();

        // Parse month
        if (!preg_match('/^(\d{4})-(\d{2})$/', $request->month, $matches)) {
            $error = ['month' => 'Nevažeći format mjeseca. Koristite YYYY-MM.'];
            if ($isAjaxRequest) {
                return response()->json(['message' => 'Validation failed', 'errors' => $error], 422);
            }
            return back()->withErrors($error);
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];

        // Validate month range
        if ($month < 1 || $month > 12) {
            $error = ['month' => 'Nevažeći mjesec.'];
            if ($isAjaxRequest) {
                return response()->json(['message' => 'Validation failed', 'errors' => $error], 422);
            }
            return back()->withErrors($error);
        }

        // Query invoices for selected members filtered by month
        $invoices = Invoice::with(['member', 'workshop', 'membershipPlan'])
            ->whereIn('member_id', $request->member_ids)
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month)
            ->get();

        if ($invoices->isEmpty()) {
            $error = ['month' => 'Nema računa za odabrane članove u tom mjesecu.'];
            if ($isAjaxRequest) {
                return response()->json(['message' => 'No invoices found', 'errors' => $error], 404);
            }
            return back()->withErrors($error);
        }

        // Create temporary directory for ZIP file
        $tempDir = storage_path('app/temp/slips');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Generate ZIP filename
        $groupName = preg_replace('/[^a-z0-9]+/', '-', strtolower($memberGroup->name));
        $zipFileName = 'uplatnice-grupa-' . $groupName . '-' . $request->month . '.zip';
        $zipPath = $tempDir . '/' . $zipFileName;

        // Create ZIP archive
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $error = ['error' => 'Ne mogu kreirati ZIP datoteku.'];
            if ($isAjaxRequest) {
                return response()->json(['message' => 'Failed to create ZIP file', 'errors' => $error], 500);
            }
            return back()->withErrors($error);
        }

        // Generate PDFs and add to ZIP
        $invoiceController = new InvoiceController();
        $addedCount = 0;

        foreach ($invoices as $invoice) {
            try {
                $pdf = $invoiceController->generateSlipPDF($invoice);
                
                // Generate filename: Firstname-Lastname-referencecode.pdf
                $firstName = trim($invoice->member->first_name ?? '');
                $lastName = trim($invoice->member->last_name ?? '');
                
                // Transliterate Croatian characters to ASCII
                $firstName = $this->transliterateCroatian($firstName);
                $lastName = $this->transliterateCroatian($lastName);
                
                // Convert to lowercase, sanitize, then capitalize first letter
                $firstName = strtolower($firstName);
                $lastName = strtolower($lastName);
                $firstName = preg_replace('/[^a-z0-9]+/', '-', $firstName);
                $lastName = preg_replace('/[^a-z0-9]+/', '-', $lastName);
                // Capitalize first letter of each name
                $firstName = ucfirst($firstName);
                $lastName = ucfirst($lastName);
                $fileName = trim($firstName . '-' . $lastName . '-' . $invoice->reference_code, '-') . '.pdf';
                
                $zip->addFromString($fileName, $pdf->output());
                $addedCount++;
            } catch (\Exception $e) {
                Log::warning('Failed to generate slip PDF for invoice ' . $invoice->id . ': ' . $e->getMessage());
                // Continue with other invoices
            }
        }

        $zip->close();

        if ($addedCount === 0) {
            @unlink($zipPath); // Clean up empty ZIP
            $error = ['error' => 'Ne mogu generirati nijedan PDF.'];
            if ($isAjaxRequest) {
                return response()->json(['message' => 'Failed to generate PDFs', 'errors' => $error], 500);
            }
            return back()->withErrors($error);
        }

        // Clean up old ZIP files (older than 1 hour)
        $this->cleanupOldZipFiles($tempDir, 3600);

        // Return ZIP file as download with proper headers
        return response()->download($zipPath, $zipFileName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Transliterate Croatian characters to ASCII equivalents.
     * 
     * @param string $text
     * @return string
     */
    private function transliterateCroatian(string $text): string
    {
        // Handle multi-character sequences first (DŽ, dž)
        $text = str_replace(['DŽ', 'dž', 'Dž'], ['DJ', 'dj', 'Dj'], $text);
        
        // Handle single characters
        $transliteration = [
            'Č' => 'C', 'č' => 'c',
            'Ć' => 'C', 'ć' => 'c',
            'Đ' => 'D', 'đ' => 'd',
            'Š' => 'S', 'š' => 's',
            'Ž' => 'Z', 'ž' => 'z',
        ];
        
        return strtr($text, $transliteration);
    }

    /**
     * Clean up old ZIP files to prevent storage bloat.
     * 
     * @param string $directory
     * @param int $maxAgeSeconds Maximum age in seconds (default: 1 hour)
     * @return void
     */
    private function cleanupOldZipFiles(string $directory, int $maxAgeSeconds = 3600): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*.zip');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                $fileAge = $now - filemtime($file);
                if ($fileAge > $maxAgeSeconds) {
                    @unlink($file);
                }
            }
        }
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
