<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\Member;
use App\Models\MemberDocument;
use App\Services\DocumentGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentTemplateController extends Controller
{
    protected $documentService;

    public function __construct(DocumentGenerationService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Determine type from route name
        $routeName = $request->route()->getName();
        if (str_contains($routeName, 'ispricnice')) {
            $type = 'ispricnica';
            $componentPath = 'Documents/Ispricnice/Index';
        } elseif (str_contains($routeName, 'privole')) {
            $type = 'privola';
            $componentPath = 'Documents/Privole/Index';
        } else {
            $type = $request->input('type', 'ispricnica');
            $componentPath = 'Documents/Ispricnice/Index';
        }
        
        $templates = DocumentTemplate::where('type', $type)
            ->orderBy('created_at', 'desc')
            ->get();

        return inertia($componentPath, [
            'templates' => $templates,
            'type' => $type,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Determine type from route name
        $routeName = $request->route()->getName();
        if (str_contains($routeName, 'ispricnice')) {
            $type = 'ispricnica';
            $componentPath = 'Documents/Ispricnice/Create';
        } elseif (str_contains($routeName, 'privole')) {
            $type = 'privola';
            $componentPath = 'Documents/Privole/Create';
        } else {
            $type = $request->input('type', 'ispricnica');
            $componentPath = 'Documents/Ispricnice/Create';
        }
        
        $template = new DocumentTemplate();
        $template->type = $type;
        $placeholders = $template->getAvailablePlaceholders();

        return inertia($componentPath, [
            'template' => $template,
            'placeholders' => $placeholders,
            'type' => $type,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:ispricnica,privola',
            'name' => 'required|string|max:255',
            'content' => 'required|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        DocumentTemplate::create($validated);

        $routeName = $validated['type'] === 'ispricnica' 
            ? 'document-templates.ispricnice.index' 
            : 'document-templates.privole.index';
        
        return redirect()
            ->route($routeName)
            ->with('success', 'Predložak uspješno kreiran.');
    }

    /**
     * Display the specified resource.
     */
    public function show(DocumentTemplate $documentTemplate)
    {
        $placeholders = $documentTemplate->getAvailablePlaceholders();
        
        // Determine component path based on type
        $componentPath = $documentTemplate->type === 'ispricnica' 
            ? 'Documents/Ispricnice/Create' 
            : 'Documents/Privole/Create';

        return inertia($componentPath, [
            'template' => $documentTemplate,
            'placeholders' => $placeholders,
            'type' => $documentTemplate->type,
            'isEdit' => true,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DocumentTemplate $documentTemplate)
    {
        $placeholders = $documentTemplate->getAvailablePlaceholders();
        
        // Determine component path based on type
        $componentPath = $documentTemplate->type === 'ispricnica' 
            ? 'Documents/Ispricnice/Create' 
            : 'Documents/Privole/Create';

        return inertia($componentPath, [
            'template' => $documentTemplate,
            'placeholders' => $placeholders,
            'type' => $documentTemplate->type,
            'isEdit' => true,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $documentTemplate->update($validated);

        $routeName = $documentTemplate->type === 'ispricnica' 
            ? 'document-templates.ispricnice.index' 
            : 'document-templates.privole.index';

        return redirect()
            ->route($routeName)
            ->with('success', 'Predložak uspješno ažuriran.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DocumentTemplate $documentTemplate): RedirectResponse
    {
        $type = $documentTemplate->type;
        $documentTemplate->delete();

        $routeName = $type === 'ispricnica' 
            ? 'document-templates.ispricnice.index' 
            : 'document-templates.privole.index';

        return redirect()
            ->route($routeName)
            ->with('success', 'Predložak uspješno obrisan.');
    }

    /**
     * Preview template with sample data as PDF.
     */
    public function preview(Request $request, ?DocumentTemplate $documentTemplate = null)
    {
        // Use content from request if provided (for new templates), otherwise use template content
        $content = $request->input('content');
        $type = $request->input('type', $documentTemplate?->type ?? 'ispricnica');
        
        if (!$content && $documentTemplate) {
            $content = $documentTemplate->content;
        }

        if (!$content) {
            abort(400, 'Content is required');
        }

        // Create a temporary template object for preview
        $tempTemplate = $documentTemplate ?? new DocumentTemplate([
            'type' => $type,
            'content' => $content,
            'name' => 'Preview',
        ]);

        // Create a sample member for preview
        $sampleMember = new Member([
            'first_name' => 'Ivan',
            'last_name' => 'Horvat',
            'parent_contact' => 'Roditelj Ime Prezime',
            'parent_email' => 'roditelj@example.com',
            'date_of_birth' => '2010-05-15',
            'email' => 'ivan.horvat@example.com',
            'phone_number' => '+385 99 123 4567',
        ]);

        $additionalData = [];
        if ($type === 'privola') {
            $additionalData['activity'] = [
                'name' => 'Razmjena mladih u Ljubljani',
                'start_date' => '2025-08-03',
                'end_date' => '2025-08-15',
                'location' => 'Ljubljana, Slovenija',
                'description' => 'Program razmjene mladih',
            ];
        }

        // Generate PDF preview
        $pdf = $this->documentService->generatePDF(
            $tempTemplate,
            $sampleMember,
            $additionalData
        );

        $filename = 'preview_' . time() . '.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Generate PDF for a single member.
     */
    public function generate(Request $request, DocumentTemplate $documentTemplate)
    {
        $validated = $request->validate([
            'member_id' => 'required|exists:members,id',
            'activity' => 'nullable|array',
            'custom' => 'nullable|array',
        ]);

        $member = Member::findOrFail($validated['member_id']);
        
        $additionalData = [];
        if (isset($validated['activity'])) {
            $additionalData['activity'] = $validated['activity'];
        }
        if (isset($validated['custom'])) {
            $additionalData['custom'] = $validated['custom'];
        }

        // Check if document already exists for this member and template
        $existingDocument = MemberDocument::where('member_id', $member->id)
            ->where('document_template_id', $documentTemplate->id)
            ->where('document_type', $documentTemplate->type)
            ->first();

        // Only create record if it doesn't exist
        if (!$existingDocument) {
            MemberDocument::create([
                'member_id' => $member->id,
                'document_template_id' => $documentTemplate->id,
                'document_type' => $documentTemplate->type,
                'additional_data' => $additionalData,
            ]);
        }

        // Always generate PDF on-demand (no storage, just stream)
        $pdf = $this->documentService->generatePDF($documentTemplate, $member, $additionalData);
        $filename = str_replace([' ', '/', '\\'], '_', $documentTemplate->name . '_' . trim(($member->first_name ?? '') . '_' . ($member->last_name ?? ''))) . '.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Generate PDFs for multiple members.
     */
    public function generateBulk(Request $request, DocumentTemplate $documentTemplate)
    {
        $validated = $request->validate([
            'member_ids' => 'required|array',
            'member_ids.*' => 'exists:members,id',
            'activity' => 'nullable|array',
            'custom' => 'nullable|array',
        ]);

        $members = Member::whereIn('id', $validated['member_ids'])->get();
        
        $additionalData = [];
        if (isset($validated['activity'])) {
            $additionalData['activity'] = $validated['activity'];
        }
        if (isset($validated['custom'])) {
            $additionalData['custom'] = $validated['custom'];
        }

        // Save document records for all members (only if they don't exist)
        foreach ($members as $member) {
            $existingDocument = MemberDocument::where('member_id', $member->id)
                ->where('document_template_id', $documentTemplate->id)
                ->where('document_type', $documentTemplate->type)
                ->first();

            if (!$existingDocument) {
                MemberDocument::create([
                    'member_id' => $member->id,
                    'document_template_id' => $documentTemplate->id,
                    'document_type' => $documentTemplate->type,
                    'additional_data' => $additionalData,
                ]);
            }
        }

        // Return success response - documents are saved to member profiles
        return response()->json([
            'success' => true,
            'message' => 'Dokumenti su uspješno generirani i spremljeni.',
            'count' => $members->count(),
        ]);
    }

    /**
     * Get active templates by type.
     */
    public function getActiveTemplates(Request $request)
    {
        $type = $request->input('type', 'ispricnica');
        
        $templates = DocumentTemplate::where('type', $type)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return response()->json($templates->toArray());
    }

    /**
     * Delete a member document.
     */
    public function deleteMemberDocument(MemberDocument $memberDocument)
    {
        $memberDocument->delete();

        if (request()->header('X-Inertia')) {
            return back()->with('success', 'Dokument uspješno obrisan.');
        }

        return redirect()->back()->with('success', 'Dokument uspješno obrisan.');
    }

    /**
     * Download/View a member document.
     */
    public function downloadMemberDocument(MemberDocument $memberDocument)
    {
        $memberDocument->load(['member', 'documentTemplate']);
        
        $member = $memberDocument->member;
        $template = $memberDocument->documentTemplate;
        $additionalData = $memberDocument->additional_data ?? [];

        $pdf = $this->documentService->generatePDF($template, $member, $additionalData);
        $filename = str_replace([' ', '/', '\\'], '_', $template->name . '_' . trim(($member->first_name ?? '') . '_' . ($member->last_name ?? ''))) . '.pdf';

        return $pdf->stream($filename);
    }
}
