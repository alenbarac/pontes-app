<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportMBankingStatementsRequest;
use App\Imports\MBankingStatementsImport;

class InvoiceImportController extends Controller
{
    public function index()
    {
        return inertia('Invoices/ImportMBanking');
    }

    public function store(ImportMBankingStatementsRequest $request)
    {
        $file = $request->file('file');
        $import = new MBankingStatementsImport();

        try {
            $import->import($file->getRealPath());

            return inertia('Invoices/ImportMBanking', [
                'importResult' => $import->getResults(),
            ]);
        } catch (\Throwable $exception) {
            return back()->withErrors([
                'file' => 'Greška pri obradi izvoda: ' . $exception->getMessage(),
            ]);
        }
    }
}
