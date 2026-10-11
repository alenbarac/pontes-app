<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportMBankingStatementsRequest;
use App\Imports\MBankingStatementsImport;
use App\Models\BankStatementImport;
use App\Models\BankStatementImportLine;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\BankStatementReview;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InvoiceImportController extends Controller
{
    public function __construct(private BankStatementReview $review) {}

    public function index()
    {
        $latest = BankStatementImport::query()
            ->latest('imported_at')
            ->latest('id')
            ->withCount([
                'lines as open_mismatch_count' => fn ($query) => $query
                    ->where('kind', BankStatementImportLine::KIND_MISMATCH)
                    ->where('resolution', BankStatementImportLine::RESOLUTION_OPEN),
                'lines as open_unresolved_count' => fn ($query) => $query
                    ->where('kind', BankStatementImportLine::KIND_UNRESOLVED)
                    ->where('resolution', BankStatementImportLine::RESOLUTION_OPEN),
                'lines as open_ambiguous_count' => fn ($query) => $query
                    ->where('kind', BankStatementImportLine::KIND_AMBIGUOUS)
                    ->where('resolution', BankStatementImportLine::RESOLUTION_OPEN),
            ])
            ->first();

        return inertia('Invoices/ImportMBanking', [
            'latestImport' => $latest ? [
                'id' => $latest->id,
                'filename' => $latest->filename,
                'imported_at' => $latest->imported_at?->format('d.m.Y. H:i'),
                'open_mismatch_count' => $latest->open_mismatch_count,
                'open_unresolved_count' => $latest->open_unresolved_count,
                'open_ambiguous_count' => $latest->open_ambiguous_count,
            ] : null,
        ]);
    }

    public function store(ImportMBankingStatementsRequest $request)
    {
        $file = $request->file('file');
        $import = new MBankingStatementsImport;

        try {
            $import->import($file->getRealPath());
        } catch (\Throwable $exception) {
            return back()->withErrors([
                'file' => 'Greška pri obradi izvoda: '.$exception->getMessage(),
            ]);
        }

        $record = $this->review->store(
            $import->getResults(),
            $file->getClientOriginalName(),
            $request->user()?->id,
        );

        return redirect()->route('invoices.importMbanking.show', $record);
    }

    public function show(BankStatementImport $bankStatementImport)
    {
        return inertia('Invoices/ImportMBankingReview', $this->review->present($bankStatementImport));
    }

    public function align(BankStatementImport $bankStatementImport, BankStatementImportLine $bankStatementImportLine)
    {
        $this->authorizeLine($bankStatementImport, $bankStatementImportLine);
        $this->review->align($bankStatementImportLine);

        return redirect()
            ->route('invoices.importMbanking.show', $bankStatementImport)
            ->with('success', 'Račun je usklađen s uplatom i označen plaćenim.');
    }

    public function applyPlan(
        Request $request,
        BankStatementImport $bankStatementImport,
        BankStatementImportLine $bankStatementImportLine
    ) {
        $this->authorizeLine($bankStatementImport, $bankStatementImportLine);

        $validated = $request->validate([
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
        ]);

        $this->review->applyPlan(
            $bankStatementImportLine,
            MembershipPlan::query()->findOrFail($validated['membership_plan_id'])
        );

        return redirect()
            ->route('invoices.importMbanking.show', $bankStatementImport)
            ->with('success', 'Plan je postavljen, a račun je usklađen s uplatom.');
    }

    public function attach(
        Request $request,
        BankStatementImport $bankStatementImport,
        BankStatementImportLine $bankStatementImportLine
    ) {
        $this->authorizeLine($bankStatementImport, $bankStatementImportLine);

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
        ]);

        $message = $this->review->attach(
            $bankStatementImportLine,
            Invoice::query()->findOrFail($validated['invoice_id'])
        );

        return redirect()
            ->route('invoices.importMbanking.show', $bankStatementImport)
            ->with('success', $message);
    }

    public function dismiss(BankStatementImport $bankStatementImport, BankStatementImportLine $bankStatementImportLine)
    {
        $this->authorizeLine($bankStatementImport, $bankStatementImportLine);
        $this->review->dismiss($bankStatementImportLine);

        return redirect()
            ->route('invoices.importMbanking.show', $bankStatementImport)
            ->with('success', 'Stavka je odbačena.');
    }

    public function updateNote(
        Request $request,
        BankStatementImport $bankStatementImport,
        BankStatementImportLine $bankStatementImportLine
    ) {
        $this->authorizeLine($bankStatementImport, $bankStatementImportLine);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->review->saveNote($bankStatementImportLine, $validated['note'] ?? null);

        return redirect()
            ->route('invoices.importMbanking.show', $bankStatementImport)
            ->with('success', 'Bilješka je spremljena.');
    }

    public function generateInvoice(
        Request $request,
        BankStatementImport $bankStatementImport,
        BankStatementImportLine $bankStatementImportLine
    ) {
        $this->authorizeLine($bankStatementImport, $bankStatementImportLine);

        $validated = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'workshop_id' => ['required', 'integer', 'exists:workshops,id'],
            'target_month' => ['required', 'date_format:Y-m'],
        ]);

        $invoice = $this->review->generateInvoice(
            $bankStatementImportLine,
            Member::query()->findOrFail($validated['member_id']),
            (int) $validated['workshop_id'],
            $validated['target_month']
        );

        $label = $invoice->due_date
            ? Carbon::parse($invoice->due_date)->format('m/Y')
            : $validated['target_month'];

        return redirect()
            ->route('invoices.importMbanking.show', $bankStatementImport)
            ->with('success', "Račun {$invoice->reference_code} za {$label} je generiran. Povežite ga s uplatom.");
    }

    public function linkMember(
        Request $request,
        BankStatementImport $bankStatementImport,
        BankStatementImportLine $bankStatementImportLine
    ) {
        $this->authorizeLine($bankStatementImport, $bankStatementImportLine);

        $validated = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ]);

        $this->review->linkMember(
            $bankStatementImportLine,
            Member::query()->findOrFail($validated['member_id'])
        );

        return redirect()
            ->route('invoices.importMbanking.show', $bankStatementImport)
            ->with('success', 'Uplata je povezana s članom.');
    }

    private function authorizeLine(BankStatementImport $import, BankStatementImportLine $line): void
    {
        abort_unless($line->bank_statement_import_id === $import->id, 404);
    }
}
