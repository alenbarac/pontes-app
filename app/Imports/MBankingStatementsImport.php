<?php

namespace App\Imports;

use App\Models\Invoice;
use App\Support\PayerNameMatcher;
use App\Support\PaymentMonth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MBankingStatementsImport
{
    private const STATUS_PAID = 'Plaćeno';

    private const STATUS_MISMATCH = 'Neusklađeno';

    private int $totalRows = 0;

    private int $incomingRows = 0;

    private int $paidCount = 0;

    private int $mismatchCount = 0;

    private int $failedCount = 0;

    private int $skippedCount = 0;

    private int $unresolvedCount = 0;

    private int $ambiguousCount = 0;

    private array $errors = [];

    private array $matches = [];

    private array $lines = [];

    private array $processedInvoiceIds = [];

    public function __construct(private PayerNameMatcher $names = new PayerNameMatcher) {}

    public function import(string $filePath): void
    {
        $content = @file_get_contents($filePath);
        if ($content === false || $content === '') {
            throw new \RuntimeException('Datoteka je prazna ili nečitljiva.');
        }

        $content = $this->toUtf8($content);
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        if (empty($lines)) {
            throw new \RuntimeException('Datoteka je prazna ili nečitljiva.');
        }

        [$headerMap, $rows] = $this->parseRows($lines);

        foreach ($rows as $entry) {
            $this->totalRows++;
            $rowNumber = $entry['row_number'];
            $row = $entry['data'];

            $amount = $this->parseAmount($row[$headerMap['uplate']] ?? null);
            if ($amount <= 0) {
                $this->skippedCount++;

                continue;
            }

            $this->incomingRows++;

            $payerName = trim((string) ($row[$headerMap['platitelj_primatelj']] ?? ''));
            $valueDate = $this->parseCroatianDate($row[$headerMap['datum_valute']] ?? null);
            $pnbPrimatelja = trim((string) ($row[$headerMap['pnb_primatelja']] ?? ''));
            $descriptionIndex = $headerMap['opis_placanja_tecaj'] ?? null;
            $paymentDescription = $descriptionIndex === null
                ? ''
                : trim((string) ($row[$descriptionIndex] ?? ''));
            $reference = $this->extractReference($pnbPrimatelja);
            $targetMonth = $this->extractTargetMonthFromDescription($paymentDescription);

            [$invoice, $matchError, $candidateIds] = $this->findInvoiceMatch(
                $reference,
                $payerName,
                $amount,
                $valueDate,
                $targetMonth
            );

            if (! $invoice) {
                $this->failedCount++;
                $isAmbiguous = $matchError === 'ambiguous';
                if ($isAmbiguous) {
                    $this->ambiguousCount++;
                } else {
                    $this->unresolvedCount++;
                }

                $message = $isAmbiguous
                    ? 'Pronađeno je više mogućih računa za ovu uplatu.'
                    : 'Nije pronađen odgovarajući račun.';

                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => $message,
                    'reference' => $reference,
                    'payer' => $payerName,
                    'amount' => $amount,
                ];
                $this->lines[] = $this->makeLine(
                    $rowNumber,
                    $isAmbiguous ? 'ambiguous' : 'unresolved',
                    $payerName,
                    $amount,
                    null,
                    $reference,
                    $valueDate,
                    $paymentDescription,
                    $message,
                    null,
                    null,
                    $candidateIds
                );

                continue;
            }

            if (isset($this->processedInvoiceIds[$invoice->id])) {
                $this->failedCount++;
                $message = 'Račun je već obrađen u istoj datoteci.';
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => $message,
                    'reference' => $invoice->reference_code,
                    'payer' => $payerName,
                    'amount' => $amount,
                ];
                $this->lines[] = $this->makeLine(
                    $rowNumber,
                    'unresolved',
                    $payerName,
                    $amount,
                    (float) $invoice->amount_due,
                    $invoice->reference_code,
                    $valueDate,
                    $paymentDescription,
                    $message,
                    $invoice->member_id,
                    null,
                    []
                );

                continue;
            }

            $this->processedInvoiceIds[$invoice->id] = true;
            $resultStatus = $this->applyPaymentRules($invoice, $amount);

            $memberName = trim(($invoice->member->first_name ?? '').' '.($invoice->member->last_name ?? ''));
            $this->matches[] = [
                'row' => $rowNumber,
                'payer' => $payerName,
                'member' => $memberName,
                'reference' => $invoice->reference_code,
                'amount_due' => (float) $invoice->amount_due,
                'amount_paid' => $amount,
                'status' => $resultStatus,
            ];
            $this->lines[] = $this->makeLine(
                $rowNumber,
                $resultStatus === self::STATUS_PAID ? 'paid' : 'mismatch',
                $payerName,
                $amount,
                (float) $invoice->amount_due,
                $invoice->reference_code,
                $valueDate,
                $paymentDescription,
                null,
                $invoice->member_id,
                $invoice->id,
                []
            );
        }
    }

    public function getResults(): array
    {
        return [
            'total_rows' => $this->totalRows,
            'incoming_rows' => $this->incomingRows,
            'paid_count' => $this->paidCount,
            'mismatch_count' => $this->mismatchCount,
            'failed_count' => $this->failedCount,
            'skipped_count' => $this->skippedCount,
            'unresolved_count' => $this->unresolvedCount,
            'ambiguous_count' => $this->ambiguousCount,
            'errors' => $this->errors,
            'matches' => $this->matches,
            'lines' => $this->lines,
        ];
    }

    private function makeLine(
        int $rowNumber,
        string $kind,
        string $payer,
        float $amountPaid,
        ?float $amountDue,
        ?string $reference,
        ?Carbon $valueDate,
        string $description,
        ?string $message,
        ?int $memberId,
        ?int $invoiceId,
        array $candidateIds
    ): array {
        return [
            'row_number' => $rowNumber,
            'kind' => $kind,
            'payer' => $payer,
            'amount_paid' => $amountPaid,
            'amount_due' => $amountDue,
            'reference' => $reference,
            'value_date' => $valueDate?->toDateString(),
            'description' => $description !== '' ? $description : null,
            'message' => $message,
            'member_id' => $memberId,
            'invoice_id' => $invoiceId,
            'candidate_invoice_ids' => array_values($candidateIds),
        ];
    }

    private function parseRows(array $lines): array
    {
        $headerLineIndex = null;
        foreach ($lines as $index => $line) {
            $normalized = Str::lower($line);
            if (str_contains($normalized, 'redni broj') && str_contains($normalized, 'uplate')) {
                $headerLineIndex = $index;
                break;
            }
        }

        if ($headerLineIndex === null) {
            throw new \RuntimeException('CSV zaglavlje nije pronađeno.');
        }

        $delimiter = $this->detectDelimiter($lines[$headerLineIndex]);
        $headers = str_getcsv($lines[$headerLineIndex], $delimiter);
        $headerMap = $this->buildHeaderMap($headers);

        $requiredHeaders = ['datum_valute', 'uplate', 'pnb_primatelja', 'platitelj_primatelj'];
        foreach ($requiredHeaders as $requiredHeader) {
            if (! array_key_exists($requiredHeader, $headerMap)) {
                throw new \RuntimeException('CSV ne sadrži obaveznu kolonu: '.$requiredHeader);
            }
        }

        $parsedRows = [];
        for ($i = $headerLineIndex + 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '') {
                continue;
            }

            $cells = str_getcsv($lines[$i], $delimiter);
            if (count($cells) < 2) {
                continue;
            }

            $parsedRows[] = [
                'row_number' => $i + 1,
                'data' => $cells,
            ];
        }

        return [$headerMap, $parsedRows];
    }

    private function toUtf8(string $content): string
    {
        $bom = substr($content, 0, 2);
        if ($bom === "\xFF\xFE") {
            return mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        }
        if ($bom === "\xFE\xFF") {
            return mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        }
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            return substr($content, 3);
        }

        $available = array_map('strtolower', mb_list_encodings());
        $encodings = array_values(array_filter(
            ['UTF-8', 'Windows-1250', 'ISO-8859-2'],
            fn (string $encoding) => in_array(strtolower($encoding), $available, true)
        ));

        if ($encodings === []) {
            return $content;
        }

        return mb_convert_encoding($content, 'UTF-8', implode(',', $encodings));
    }

    private function detectDelimiter(string $line): string
    {
        $tabCount = substr_count($line, "\t");
        $semicolonCount = substr_count($line, ';');
        $commaCount = substr_count($line, ',');

        if ($tabCount >= $semicolonCount && $tabCount >= $commaCount) {
            return "\t";
        }

        return $semicolonCount >= $commaCount ? ';' : ',';
    }

    private function buildHeaderMap(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader($header);
            $map[$normalized] = $index;
        }

        return $map;
    }

    private function normalizeHeader(string $header): string
    {
        $header = Str::ascii(Str::lower(trim($header)));
        $header = str_replace(['"', "'", '.', ',', ':', ';', '-', '/'], ' ', $header);
        $header = preg_replace('/\s+/', '_', $header);

        if ($header === 'platitelj_primatelj') {
            return 'platitelj_primatelj';
        }
        if ($header === 'pnb_primatelja') {
            return 'pnb_primatelja';
        }

        return trim($header, '_');
    }

    private function parseAmount(?string $value): float
    {
        $amount = trim((string) $value);
        if ($amount === '') {
            return 0.0;
        }

        $amount = str_replace(['.', ' '], '', $amount);
        $amount = str_replace(',', '.', $amount);

        return is_numeric($amount) ? (float) $amount : 0.0;
    }

    private function parseCroatianDate(?string $value): ?Carbon
    {
        $date = trim((string) $value);
        if ($date === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('j.n.Y.', $date)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractReference(string $pnbPrimatelja): ?string
    {
        if ($pnbPrimatelja === '') {
            return null;
        }

        if (preg_match('/(\d{6}-\d{3}-\d{3})/', $pnbPrimatelja, $matches) === 1) {
            return $matches[1];
        }

        $normalized = strtoupper(trim($pnbPrimatelja));
        $compact = preg_replace('/[^A-Z0-9]/', '', $normalized);
        $compact = preg_replace('/^HRHR\d{2}/', '', $compact);
        $digitsOnly = preg_replace('/\D/', '', $compact);

        // Common bank-export variant: reference without separators, e.g. 202602101001
        if (strlen($digitsOnly) === 12) {
            return substr($digitsOnly, 0, 6).'-'.
                substr($digitsOnly, 6, 3).'-'.
                substr($digitsOnly, 9, 3);
        }

        return null;
    }

    private function findInvoiceMatch(
        ?string $reference,
        string $payerName,
        float $amount,
        ?Carbon $valueDate,
        ?Carbon $targetMonth
    ): array {
        if ($reference) {
            $referenceKey = $this->normalizeReferenceKey($reference);
            $invoice = Invoice::query()
                ->where(function ($query) use ($reference, $referenceKey) {
                    $query->where('reference_code', $reference)
                        ->orWhereRaw(
                            "REPLACE(REPLACE(UPPER(reference_code), '-', ''), ' ', '') = ?",
                            [$referenceKey]
                        );
                })
                ->first();

            if ($invoice) {
                return [$invoice, null, []];
            }
        }

        if (! $valueDate || trim($payerName) === '') {
            return [null, 'unresolved', []];
        }

        $candidateQuery = Invoice::query()
            ->with('member:id,first_name,last_name')
            ->whereBetween('amount_due', [$amount - 0.01, $amount + 0.01]);

        if ($targetMonth) {
            $candidateQuery
                ->whereYear('due_date', $targetMonth->year)
                ->whereMonth('due_date', $targetMonth->month);
        } else {
            $candidateQuery->whereBetween('due_date', [
                $valueDate->copy()->subMonths(2)->startOfMonth()->toDateString(),
                $valueDate->copy()->addMonth()->endOfMonth()->toDateString(),
            ]);
        }

        $candidates = $candidateQuery->get();

        if ($candidates->isEmpty()) {
            return [null, 'unresolved', []];
        }

        $scored = [];
        foreach ($candidates as $candidate) {
            $fullName = trim(($candidate->member->first_name ?? '').' '.($candidate->member->last_name ?? ''));
            $score = $this->names->score($payerName, $fullName);

            if ($score > 0) {
                $scored[] = [
                    'invoice' => $candidate,
                    'score' => $score,
                ];
            }
        }

        if (empty($scored)) {
            return [null, 'unresolved', []];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $bestScore = $scored[0]['score'];
        $bestCandidates = array_values(array_filter(
            $scored,
            fn ($item) => $item['score'] === $bestScore
        ));

        if (count($bestCandidates) > 1) {
            return [
                null,
                'ambiguous',
                array_map(fn ($item) => $item['invoice']->id, $bestCandidates),
            ];
        }

        return [$bestCandidates[0]['invoice'], null, []];
    }

    private function normalizeReferenceKey(string $reference): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($reference)));
    }

    private function extractTargetMonthFromDescription(string $description): ?Carbon
    {
        return PaymentMonth::fromDescription($description);
    }

    private function applyPaymentRules(Invoice $invoice, float $amount): string
    {
        $exactAmountMatch = abs((float) $invoice->amount_due - $amount) < 0.01;

        DB::transaction(function () use ($invoice, $amount, $exactAmountMatch) {
            $invoice->amount_paid = $amount;
            $invoice->payment_status = $exactAmountMatch
                ? self::STATUS_PAID
                : self::STATUS_MISMATCH;
            $invoice->save();
        });

        if ($exactAmountMatch) {
            $this->paidCount++;

            return self::STATUS_PAID;
        }

        $this->mismatchCount++;

        return self::STATUS_MISMATCH;
    }
}
