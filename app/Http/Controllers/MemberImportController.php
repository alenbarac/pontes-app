<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportMembersRequest;
use App\Imports\MembersImport;
use Maatwebsite\Excel\Facades\Excel;

class MemberImportController extends Controller
{
    public function index()
    {
        return inertia('Members/Import');
    }

    public function store(ImportMembersRequest $request)
    {
        $file = $request->file('file');

        $import = new MembersImport();
        
        try {
            Excel::import($import, $file);
            
            $results = $import->getResults();

            return inertia('Members/Import', [
                'importResult' => $results,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors([
                'file' => 'Greška pri učitavanju datoteke: ' . $e->getMessage(),
            ]);
        }
    }

    public function template()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="predlozak_uvoz_clanova.csv"',
        ];

        $csvData = [
            ['GRUPA', 'IME I PREZIME', 'E-MAIL', 'ČLANARINA'],
            ['Grupa 1', 'Ivan Horvat', 'ivan.horvat@example.com', 'Mjesečna članarina'],
            ['Grupa 2', 'Ana Marić', 'ana.maric@example.com', 'Godišnja članarina'],
        ];

        $callback = function () use ($csvData) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8 to ensure proper encoding in Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
