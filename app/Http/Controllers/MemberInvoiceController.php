<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Workshop;
use App\Services\InvoiceGenerationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class MemberInvoiceController extends Controller
{
    protected $invoiceService;

    public function __construct(InvoiceGenerationService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Generate a session invoice for individual counseling.
     * 
     * @param Request $request
     * @param Member $member
     * @return JsonResponse
     */
    public function generateSessionInvoice(Request $request, Member $member): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'workshop_id' => 'required|exists:workshops,id',
            'session_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $workshop = Workshop::findOrFail($request->workshop_id);

            // Verify this is an individual counseling workshop
            if (strtolower($workshop->type) !== 'individualno' && 
                !str_contains(strtolower($workshop->name), 'individualno')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session invoices can only be generated for individual counseling workshops.',
                ], 422);
            }

            // Verify member is enrolled in this workshop
            $memberWorkshop = $member->workshops()
                ->where('workshop_id', $workshop->id)
                ->first();

            if (!$memberWorkshop) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member is not enrolled in this workshop.',
                ], 422);
            }

            $sessionDate = Carbon::parse($request->session_date);
            $amount = (float) $request->amount;
            $notes = $request->notes;

            $invoice = $this->invoiceService->generateSessionInvoice(
                $member,
                $workshop,
                $sessionDate,
                $amount,
                $notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Invoice generated successfully.',
                'invoice' => [
                    'id' => $invoice->id,
                    'reference_code' => $invoice->reference_code,
                    'amount_due' => (float) $invoice->amount_due,
                    'due_date' => $invoice->due_date,
                    'session_date' => $invoice->session_date,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate invoice: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview a session invoice before generation.
     * 
     * @param Request $request
     * @param Member $member
     * @return JsonResponse
     */
    public function previewSessionInvoice(Request $request, Member $member): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'workshop_id' => 'required|exists:workshops,id',
            'session_date' => 'required|date',
            'amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $workshop = Workshop::findOrFail($request->workshop_id);

            // Verify this is an individual counseling workshop
            if (strtolower($workshop->type) !== 'individualno' && 
                !str_contains(strtolower($workshop->name), 'individualno')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session invoices can only be generated for individual counseling workshops.',
                ], 422);
            }

            // Verify member is enrolled in this workshop
            $memberWorkshop = $member->workshops()
                ->where('workshop_id', $workshop->id)
                ->first();

            if (!$memberWorkshop) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member is not enrolled in this workshop.',
                ], 422);
            }

            $sessionDate = Carbon::parse($request->session_date);
            
            // Calculate default amount if not provided
            $amount = $request->amount 
                ? (float) $request->amount 
                : $this->invoiceService->calculateSessionAmount($member, $workshop);

            return response()->json([
                'success' => true,
                'preview' => [
                    'member_name' => $member->first_name . ' ' . $member->last_name,
                    'workshop_name' => $workshop->name,
                    'session_date' => $sessionDate->format('Y-m-d'),
                    'session_date_formatted' => $sessionDate->format('d.m.Y'),
                    'amount' => $amount,
                    'default_amount' => $this->invoiceService->calculateSessionAmount($member, $workshop),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to preview invoice: ' . $e->getMessage(),
            ], 500);
        }
    }
}
