<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Workshop;
use App\Models\MemberGroup;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class InvoiceController extends Controller
{
    
    public function index(Request $request)
{
    $perPage = $request->get('per_page', 10);
    $filter = $request->get('filter', '');
    $workshopId = $request->get('workshop_id', '');
    $paymentStatus = $request->get('payment_status', '');
    $groupId = $request->get('group_id', '');

    $query = Invoice::with(['member', 'member.workshopGroups.group', 'workshop', 'membershipPlan'])
        ->when($filter, function ($query, $filter) {
            $query->where(function ($q) use ($filter) {
                $q->whereHas('member', fn($q) =>
                    $q->where('first_name', 'like', "%$filter%")
                      ->orWhere('last_name', 'like', "%$filter%")
                )
                ->orWhereHas('workshop', fn($q) =>
                    $q->where('name', 'like', "%$filter%")
                )
                ->orWhere('reference_code', 'like', "%$filter%")
                ->orWhere('payment_status', 'like', "%$filter%");
            });
        })
        ->when($workshopId, function ($query, $workshopId) {
            $query->where('workshop_id', $workshopId);
        })
        ->when($paymentStatus, function ($query, $paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        })
        ->when($groupId, function ($query, $groupId) use ($workshopId) {
            $query->whereHas('member.workshopGroups', function ($q) use ($groupId, $workshopId) {
                $q->where('member_group_id', $groupId);
                if ($workshopId) {
                    $q->where('workshop_id', $workshopId);
                }
            });
        })
        ->orderByDesc('due_date');

    $invoices = $query->paginate($perPage)->withQueryString();

    // Get all workshops for the filter dropdown
    $workshops = Workshop::select('id', 'name')->orderBy('name')->get();

    // Payment status options
    $paymentStatuses = ['Otvoreno', 'Plaćeno', 'Opomeni'];

    // Groups for filter (optionally scoped by workshop)
    // Some schemas don't have workshop_id on member_groups, so fall back to the mapping table workshop_groups
    $groups = MemberGroup::query()
        ->select('member_groups.id', 'member_groups.name')
        ->when($workshopId, function ($q) use ($workshopId) {
            $q->join('workshop_groups', 'workshop_groups.member_group_id', '=', 'member_groups.id')
              ->where('workshop_groups.workshop_id', $workshopId)
              ->distinct();
        })
        ->orderBy('member_groups.name')
        ->get();

    return Inertia::render('Invoices/Index', [
        'invoices' => $invoices,
        'pagination' => [
            'current_page' => $invoices->currentPage(),
            'per_page' => $invoices->perPage(),
            'total' => $invoices->total(),
            'last_page' => $invoices->lastPage(),
        ],
        'filter' => $filter,
        'workshopId' => $workshopId,
        'paymentStatus' => $paymentStatus,
        'groupId' => $groupId,
        'workshops' => $workshops,
        'paymentStatuses' => $paymentStatuses,
        'groups' => $groups,
    ]);
}
public function updateStatus(Request $request, Invoice $invoice)
{
    $validated = $request->validate([
        'status' => ['required', 'in:Plaćeno,Otvoreno'],
    ]);

    $status = $validated['status'];

    if ($status === 'Plaćeno') {
        // If you hit this endpoint instead of markPaid, set amount_paid to full
        $invoice->amount_paid = $invoice->amount_due;
    } else { // Otvoreno
        $invoice->amount_paid = 0;
    }

    $invoice->payment_status = $status;
    $invoice->save();

    // If coming from member page or Inertia request, return back instead of redirecting
    if ($request->header('X-Inertia') || $request->has('stay_on_page')) {
        return back()->with('success', 'Status uspješno promijenjen.');
    }

    return redirect()->route('invoices.index')->with('success', 'Status uspješno promijenjen.');
}
/**
 * Bulk update status for many invoices (Otvoreno or Plaćeno)
 */
public function markBulkAsPaid(Request $request)
{
    $validated = $request->validate([
        'invoice_ids' => ['required', 'array', 'min:1'],
        'invoice_ids.*' => ['integer', 'exists:invoices,id'],
    ]);

    // Only update if not already marked as paid
    $invoices = Invoice::whereIn('id', $validated['invoice_ids'])
        ->where('payment_status', '!=', 'Plaćeno')
        ->get();

    foreach ($invoices as $invoice) {
        $invoice->amount_paid = $invoice->amount_due;
        $invoice->payment_status = 'Plaćeno';
        $invoice->save();
    }

    $updatedCount = $invoices->count();
    $total = count($validated['invoice_ids']);

    if ($updatedCount === 0) {
        return redirect()->route('invoices.index')->with('info', 'Svi označeni računi su već bili plaćeni.');
    }

    return redirect()->route('invoices.index')
        ->with('success', "Označeno kao plaćeno: {$updatedCount}/{$total} računa.");
}

/**
 * Bulk toggle open/paid status for invoices
 */
public function toggleBulkInvoiceStatus(Request $request)
{
    $validated = $request->validate([
        'invoice_ids' => ['required', 'array', 'min:1'],
        'invoice_ids.*' => ['integer', 'exists:invoices,id'],
        'status' => ['required', 'in:Plaćeno,Otvoreno'],
    ]);

    $status = $validated['status'];

    $invoices = Invoice::whereIn('id', $validated['invoice_ids'])->get();

    $updatedCount = 0;

    foreach ($invoices as $invoice) {
        if ($invoice->payment_status !== $status) {
            if ($status === 'Plaćeno') {
                $invoice->amount_paid = $invoice->amount_due;
            } else { // Otvoreno
                $invoice->amount_paid = 0;
            }
            $invoice->payment_status = $status;
            $invoice->save();
            $updatedCount++;
        }
    }

    $total = count($validated['invoice_ids']);

    if ($updatedCount === 0) {
        return redirect()->route('invoices.index')->with(
            'info',
            $status === 'Plaćeno'
                ? 'Svi označeni računi su već bili plaćeni.'
                : 'Svi označeni računi su već bili otvoreni.'
        );
    }

    $statusText = $status === 'Plaćeno' ? 'plaćenih' : 'otvorenih';

    return redirect()->route('invoices.index')
        ->with('success', "Ažurirano kao {$statusText}: {$updatedCount}/{$total} računa.");
}

public function markAsPaid(Request $request, Invoice $invoice)
{
    if ($invoice->payment_status === 'Plaćeno') {
        // If coming from member page or Inertia request, return back instead of redirecting
        if ($request->header('X-Inertia') || $request->has('stay_on_page')) {
            return back()->with('info', 'Račun je već plaćen.');
        }
        return redirect()->route('invoices.index')->with('info', 'Račun je već plaćen.');
    }

    // Optional: allow override amount; by default pay in full
    $validated = $request->validate([
        'amount_paid' => ['nullable', 'numeric', 'min:0'],
    ]);

    $invoice->amount_paid    = $validated['amount_paid'] ?? $invoice->amount_due;
    $invoice->payment_status = 'Plaćeno';
    $invoice->save();

    // If coming from member page or Inertia request, return back instead of redirecting
    if ($request->header('X-Inertia') || $request->has('stay_on_page')) {
        return back()->with('success', 'Račun označen kao plaćen.');
    }

    return redirect()->route('invoices.index')->with('success', 'Račun označen kao plaćen.');
}

public function slip(Invoice $invoice)
{
    $invoice->load(['member', 'workshop', 'membershipPlan']);

    // Fallbacks so the template never breaks
    $memberFullName = trim(($invoice->member->first_name ?? '') . ' ' . ($invoice->member->last_name ?? ''));
    $memberAddress  = trim($invoice->member->address ?? '');
    $notes          = $invoice->notes ?? 'Članarina';

    $org = config('pontes');

    // Amount formatted with comma decimals (HR)
    $amount = number_format((float)$invoice->amount_due, 2, ',', '.');

    // Generate PDF417 barcode using HUB-3 API (official HUB3A barcode generator)
    // API: https://hub3.bigfish.software/api/v2
    $tempDir = storage_path('app/temp');
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    try {
        // Prepare data according to HUB-3 API format
        // Amount must be multiplied by 100 (e.g., 50.00 becomes 5000)
        $amountCents = (int) round($invoice->amount_due * 100);
        
        // Parse postal code from recipient_postal (format: "51000, Rijeka" or "51000 Rijeka")
        $recipientPostalParts = preg_split('/[\s,]+/', $org['recipient_postal'], 2);
        $recipientPostalCode = $recipientPostalParts[0] ?? '';
        $recipientPlace = $recipientPostalParts[1] ?? '';
        
        // Parse payer address (if available)
        $payerPostalParts = !empty($memberAddress) ? preg_split('/[\s,]+/', $memberAddress, 2) : ['', ''];
        $payerStreet = $payerPostalParts[0] ?? '';
        $payerPlace = $payerPostalParts[1] ?? $memberAddress;
        
        // Prepare API request data
        $apiData = [
            'renderer' => 'image',
            'options' => [
                'format' => 'png',
                'scale' => 3,      // Width of single unit (HUB3A spec)
                'ratio' => 3,      // Width to height ratio (HUB3A requires 3:1)
                'padding' => 20,
                'color' => '#000000',
                'bgColor' => '#ffffff',
            ],
            'data' => [
                'amount' => $amountCents,
                'currency' => $org['currency'], // EUR, HRK, etc.
                'sender' => [
                    'name' => mb_substr($memberFullName, 0, 30),
                    'street' => mb_substr($payerStreet, 0, 27),
                    'place' => mb_substr($payerPlace, 0, 27),
                ],
                'receiver' => [
                    'name' => mb_substr($org['recipient_name'], 0, 25),
                    'street' => mb_substr($org['recipient_address'], 0, 25),
                    'place' => mb_substr(($recipientPostalCode . ' ' . $recipientPlace), 0, 27),
                    'iban' => str_replace(' ', '', $org['recipient_iban']),
                    'model' => mb_substr(str_replace('HR', '', $org['model']), 0, 2), // Remove HR prefix if present
                    'reference' => mb_substr($invoice->reference_code, 0, 22),
                ],
                'description' => mb_substr($notes, 0, 35),
            ],
        ];
        
        // Make POST request to HUB-3 API using Laravel HTTP client
        // Note: SSL verification disabled for local development (Laragon/Windows SSL issues)
        // In production, ensure proper SSL certificates are configured
        $response = Http::timeout(10)
            ->withoutVerifying() // Disable SSL verification for local development
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'image/png',
            ])
            ->post('https://hub3.bigfish.software/api/v2/barcode', $apiData);
        
        if ($response->successful()) {
            // Save barcode image to temporary file
            $barcodePng = $response->body();
            $tempFile = $tempDir . '/pdf417_' . $invoice->id . '_' . time() . '.png';
            file_put_contents($tempFile, $barcodePng);
            $barcodePath = $tempFile;
        } else {
            throw new \Exception('HUB-3 API returned status: ' . $response->status() . ' - ' . $response->body());
        }
    } catch (\Exception $e) {
        // If API call fails, log error and set empty path
        $barcodePath = null;
        Log::warning('Failed to generate PDF417 barcode via HUB-3 API: ' . $e->getMessage());
    }
        

    $data = [
        'bgPath'         => public_path('images/uplatnica.jpg'),
        'member_name'    => $memberFullName,
        'member_address' => $memberAddress,
        'amount'         => $amount,
        'currency'       => $org['currency'],
        'due_date'       => \Carbon\Carbon::parse($invoice->due_date)->format('d.m.Y.'),
        'reference'      => $invoice->reference_code,
        'description'    => $notes,
        'recipient_name'    => $org['recipient_name'],
        'recipient_address' => $org['recipient_address'],
        'recipient_postal' => $org['recipient_postal'],
        'recipient_iban'    => $org['recipient_iban'],
        'model'            => $org['model'], // e.g. HR00
        'status'           => $invoice->payment_status, // if you want to print status badge
        'barcode_path'     => $barcodePath, // Path to Data Matrix barcode file (2D barcode)
    ];

    // Dompdf options: Unicode, images
    $pdf = Pdf::loadView('invoices.slip', $data)
        ->setPaper('a4', 'portrait');

    $file = $invoice->reference_code . '.pdf';

    // Stream in a new tab (nice for printing); change to download() if you prefer attachment
    return $pdf->stream($file);
}

}
