<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class PaymentSlipPdfService
{
    /**
     * Render the HUB-3 payment slip PDF for an invoice.
     *
     * Centralised here so InvoiceController, the bulk download flow and the
     * PaymentSlipMailable do not depend on each other to render PDFs.
     */
    public function generate(Invoice $invoice): PdfDocument
    {
        $invoice->loadMissing(['member', 'workshop', 'membershipPlan']);

        // Fallbacks so the template never breaks
        $memberFullName = trim(($invoice->member->first_name ?? '').' '.($invoice->member->last_name ?? ''));
        $slipPayerOverride = trim((string) ($invoice->member->slip_payer_name ?? ''));
        $payerDisplayName = $slipPayerOverride !== '' ? $slipPayerOverride : $memberFullName;
        $memberAddress = trim($invoice->member->address ?? '');
        $notes = $invoice->slipPaymentNotes();

        // Opis plaćanja first line is always the member. Second line is notes
        // (or per-invoice slip_description override). slip_payer_name is PLATITELJ only.
        $descriptionForBarcode = trim($memberFullName.' - '.$notes);

        $org = config('pontes');

        // Amount formatted with comma decimals (HR)
        $amount = number_format((float) $invoice->amount_due, 2, ',', '.');

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $barcodePath = null;
        try {
            // Amount must be in cents for HUB-3 API (e.g. 50.00 -> 5000)
            $amountCents = (int) round($invoice->amount_due * 100);

            // Parse postal code from recipient_postal (format: "51000, Rijeka" or "51000 Rijeka")
            $recipientPostalParts = preg_split('/[\s,]+/', $org['recipient_postal'], 2);
            $recipientPostalCode = $recipientPostalParts[0] ?? '';
            $recipientPlace = $recipientPostalParts[1] ?? '';

            $payerPostalParts = ! empty($memberAddress) ? preg_split('/[\s,]+/', $memberAddress, 2) : ['', ''];
            $payerStreet = $payerPostalParts[0] ?? '';
            $payerPlace = $payerPostalParts[1] ?? $memberAddress;

            $apiData = [
                'renderer' => 'image',
                'options' => [
                    'format' => 'png',
                    'scale' => 3,
                    'ratio' => 3,
                    'padding' => 20,
                    'color' => '#000000',
                    'bgColor' => '#ffffff',
                ],
                'data' => [
                    'amount' => $amountCents,
                    'currency' => $org['currency'],
                    'sender' => [
                        'name' => mb_substr($payerDisplayName, 0, 30),
                        'street' => mb_substr($payerStreet, 0, 27),
                        'place' => mb_substr($payerPlace, 0, 27),
                    ],
                    'receiver' => [
                        'name' => mb_substr($org['recipient_name'], 0, 25),
                        'street' => mb_substr($org['recipient_address'], 0, 25),
                        'place' => mb_substr(($recipientPostalCode.' '.$recipientPlace), 0, 27),
                        'iban' => str_replace(' ', '', $org['recipient_iban']),
                        'model' => mb_substr(str_replace('HR', '', $org['model']), 0, 2),
                        'reference' => mb_substr($invoice->reference_code, 0, 22),
                    ],
                    'description' => mb_substr($descriptionForBarcode, 0, 35),
                ],
            ];

            // SSL verification disabled for local development (Laragon/Windows). Production should configure CA bundle.
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'image/png',
                ])
                ->post('https://hub3.bigfish.software/api/v2/barcode', $apiData);

            if (! $response->successful()) {
                throw new \RuntimeException('HUB-3 API returned status: '.$response->status().' - '.$response->body());
            }

            $tempFile = $tempDir.'/pdf417_'.$invoice->id.'_'.time().'.png';
            file_put_contents($tempFile, $response->body());
            $barcodePath = $tempFile;

            $this->cleanupOldTempFiles($tempDir, 3600);
        } catch (\Throwable $e) {
            // Slip remains useful without the barcode; fail open and log.
            Log::warning('Failed to generate PDF417 barcode via HUB-3 API: '.$e->getMessage());
        }

        $data = [
            'bgPath' => public_path('images/uplatnica.jpg'),
            'member_name' => $payerDisplayName,
            'member_address' => $memberAddress,
            'amount' => $amount,
            'currency' => $org['currency'],
            'due_date' => \Carbon\Carbon::parse($invoice->due_date)->format('d.m.Y.'),
            'reference' => $invoice->reference_code,
            'member_name_for_description' => $memberFullName,
            'payment_notes' => $notes,
            'has_custom_slip_description' => trim((string) ($invoice->slip_description ?? '')) !== '',
            'recipient_name' => $org['recipient_name'],
            'recipient_address' => $org['recipient_address'],
            'recipient_postal' => $org['recipient_postal'],
            'recipient_iban' => $org['recipient_iban'],
            'model' => $org['model'],
            'status' => $invoice->payment_status,
            'barcode_path' => $barcodePath,
        ];

        return Pdf::loadView('invoices.slip', $data)->setPaper('a4', 'portrait');
    }

    /**
     * ASCII filename for a slip PDF: Firstname-Lastname-reference.pdf
     */
    public function pdfFilename(Invoice $invoice): string
    {
        $invoice->loadMissing('member');

        $firstName = $this->sanitizePersonName($invoice->member->first_name ?? '');
        $lastName = $this->sanitizePersonName($invoice->member->last_name ?? '');
        $reference = preg_replace('/[^a-zA-Z0-9\-]+/', '-', (string) $invoice->reference_code) ?? '';

        return trim($firstName.'-'.$lastName.'-'.$reference, '-').'.pdf';
    }

    /**
     * Download name for a ZIP of selected invoices, e.g. uplatnice-godisnja-clanmarina-2026-09.zip
     *
     * @param  Collection<int, Invoice>  $invoices
     */
    public function zipDownloadName(Collection $invoices): string
    {
        if (method_exists($invoices, 'loadMissing')) {
            $invoices->loadMissing('membershipPlan');
        }

        $plans = $invoices
            ->map(fn (Invoice $invoice) => $invoice->membershipPlan?->plan)
            ->filter()
            ->unique()
            ->values();

        $months = $invoices
            ->map(function (Invoice $invoice) {
                if (! $invoice->due_date) {
                    return null;
                }

                return Carbon::parse($invoice->due_date)->format('Y-m');
            })
            ->filter()
            ->unique()
            ->values();

        $planPart = $plans->count() === 1 ? $this->slugify((string) $plans->first()) : 'racuni';
        $monthPart = $months->count() === 1 ? $months->first() : now()->format('Y-m-d');

        return 'uplatnice-'.$planPart.'-'.$monthPart.'.zip';
    }

    /**
     * Build a ZIP of payment-slip PDFs. Returns the absolute path of the temp ZIP.
     *
     * @param  Collection<int, Invoice>  $invoices
     *
     * @throws \RuntimeException
     */
    public function zipForInvoices(Collection $invoices, string $zipFileName): string
    {
        $tempDir = storage_path('app/temp/slips');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $uniqueName = pathinfo($zipFileName, PATHINFO_FILENAME).'-'.uniqid('', true).'.zip';
        $zipPath = $tempDir.'/'.$uniqueName;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Ne mogu kreirati ZIP datoteku.');
        }

        $addedCount = 0;
        $usedNames = [];

        foreach ($invoices as $invoice) {
            try {
                $pdf = $this->generate($invoice);
                $fileName = $this->uniqueZipEntryName($this->pdfFilename($invoice), $usedNames);
                $zip->addFromString($fileName, $pdf->output());
                $addedCount++;
            } catch (\Throwable $e) {
                Log::warning('Failed to generate slip PDF for invoice '.$invoice->id.': '.$e->getMessage());
            }
        }

        $zip->close();

        if ($addedCount === 0) {
            @unlink($zipPath);
            throw new \RuntimeException('Ne mogu generirati nijedan PDF.');
        }

        $this->cleanupOldZipFiles($tempDir);

        return $zipPath;
    }

    protected function sanitizePersonName(string $name): string
    {
        $name = $this->transliterateCroatian(trim($name));
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/', '-', $name) ?? '';

        return ucfirst($name);
    }

    protected function slugify(string $text): string
    {
        $text = $this->transliterateCroatian($text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

        return trim($text, '-') ?: 'racuni';
    }

    protected function transliterateCroatian(string $text): string
    {
        $text = str_replace(['DŽ', 'dž', 'Dž'], ['DJ', 'dj', 'Dj'], $text);

        return strtr($text, [
            'Č' => 'C', 'č' => 'c',
            'Ć' => 'C', 'ć' => 'c',
            'Đ' => 'D', 'đ' => 'd',
            'Š' => 'S', 'š' => 's',
            'Ž' => 'Z', 'ž' => 'z',
        ]);
    }

    /**
     * @param  array<string, true>  $usedNames
     */
    protected function uniqueZipEntryName(string $fileName, array &$usedNames): string
    {
        $base = $fileName;
        $i = 1;

        while (isset($usedNames[$fileName])) {
            $i++;
            $fileName = preg_replace('/\.pdf$/i', '-'.$i.'.pdf', $base) ?? $base;
        }

        $usedNames[$fileName] = true;

        return $fileName;
    }

    protected function cleanupOldZipFiles(string $directory, int $maxAgeSeconds = 3600): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = glob($directory.'/*.zip') ?: [];
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAgeSeconds) {
                @unlink($file);
            }
        }
    }

    protected function cleanupOldTempFiles(string $directory, int $maxAgeSeconds = 3600): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = glob($directory.'/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAgeSeconds) {
                @unlink($file);
            }
        }
    }
}
