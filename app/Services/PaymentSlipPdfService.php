<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
