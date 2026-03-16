<?php

namespace App\Imports;

use App\Models\Invoice;
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
    private array $processedInvoiceIds = [];

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
            $paymentDescription = trim((string) ($row[$headerMap['opis_placanja_tecaj']] ?? ''));
            $reference = $this->extractReference($pnbPrimatelja);
            $targetMonth = $this->extractTargetMonthFromDescription($paymentDescription);

            [$invoice, $matchError] = $this->findInvoiceMatch(
                $reference,
                $payerName,
                $amount,
                $valueDate,
                $targetMonth
            );

            if (!$invoice) {
                $this->failedCount++;
                if ($matchError === 'ambiguous') {
                    $this->ambiguousCount++;
                } else {
                    $this->unresolvedCount++;
                }

                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => $matchError === 'ambiguous'
                        ? 'Pronađeno je više mogućih računa za ovu uplatu.'
                        : 'Nije pronađen odgovarajući račun.',
                    'reference' => $reference,
                    'payer' => $payerName,
                    'amount' => $amount,
                ];
                continue;
            }

            if (isset($this->processedInvoiceIds[$invoice->id])) {
                $this->failedCount++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Račun je već obrađen u istoj datoteci.',
                    'reference' => $invoice->reference_code,
                    'payer' => $payerName,
                    'amount' => $amount,
                ];
                continue;
            }

            $this->processedInvoiceIds[$invoice->id] = true;
            $resultStatus = $this->applyPaymentRules($invoice, $amount);

            $memberName = trim(($invoice->member->first_name ?? '') . ' ' . ($invoice->member->last_name ?? ''));
            $this->matches[] = [
                'row' => $rowNumber,
                'payer' => $payerName,
                'member' => $memberName,
                'reference' => $invoice->reference_code,
                'amount_due' => (float) $invoice->amount_due,
                'amount_paid' => $amount,
                'status' => $resultStatus,
            ];
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
            if (!array_key_exists($requiredHeader, $headerMap)) {
                throw new \RuntimeException('CSV ne sadrži obaveznu kolonu: ' . $requiredHeader);
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

        return mb_convert_encoding($content, 'UTF-8', 'UTF-8,Windows-1250,ISO-8859-2');
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

    private function parseAmount(null|string $value): float
    {
        $amount = trim((string) $value);
        if ($amount === '') {
            return 0.0;
        }

        $amount = str_replace(['.', ' '], '', $amount);
        $amount = str_replace(',', '.', $amount);

        return is_numeric($amount) ? (float) $amount : 0.0;
    }

    private function parseCroatianDate(null|string $value): ?Carbon
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
            return substr($digitsOnly, 0, 6) . '-' .
                substr($digitsOnly, 6, 3) . '-' .
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
                return [$invoice, null];
            }
        }

        if (!$valueDate || trim($payerName) === '') {
            return [null, 'unresolved'];
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
            return [null, 'unresolved'];
        }

        $normalizedPayer = $this->normalizeText($payerName);
        $scored = [];
        foreach ($candidates as $candidate) {
            $fullName = trim(($candidate->member->first_name ?? '') . ' ' . ($candidate->member->last_name ?? ''));
            $normalizedMemberName = $this->normalizeText($fullName);

            if ($normalizedMemberName === '') {
                continue;
            }

            $score = $this->scoreNameMatch($normalizedPayer, $normalizedMemberName);

            if ($score > 0) {
                $scored[] = [
                    'invoice' => $candidate,
                    'score' => $score,
                ];
            }
        }

        if (empty($scored)) {
            return [null, 'unresolved'];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $bestScore = $scored[0]['score'];
        $bestCandidates = array_values(array_filter(
            $scored,
            fn ($item) => $item['score'] === $bestScore
        ));

        if (count($bestCandidates) > 1) {
            return [null, 'ambiguous'];
        }

        return [$bestCandidates[0]['invoice'], null];
    }

    private function normalizeText(string $value): string
    {
        $normalized = Str::ascii(Str::lower(trim($value)));
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    private function normalizeReferenceKey(string $reference): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($reference)));
    }

    private function scoreNameMatch(string $payerName, string $memberName): int
    {
        if ($payerName === '' || $memberName === '') {
            return 0;
        }

        if ($payerName === $memberName) {
            return 5;
        }

        [$payerFirst, $payerLast] = $this->splitName($payerName);
        [$memberFirst, $memberLast] = $this->splitName($memberName);

        if ($payerLast !== '' && $memberLast !== '' && $payerLast === $memberLast) {
            if ($payerFirst === $memberFirst) {
                return 4;
            }

            // Small typo tolerance in first name (e.g. Mirjan vs Mirjana)
            if (
                levenshtein($payerFirst, $memberFirst) <= 1 ||
                str_starts_with($payerFirst, $memberFirst) ||
                str_starts_with($memberFirst, $payerFirst)
            ) {
                return 3;
            }

            return 2;
        }

        if (
            str_contains($memberName, $payerName) ||
            str_contains($payerName, $memberName)
        ) {
            return 1;
        }

        return 0;
    }

    private function splitName(string $fullName): array
    {
        $parts = array_values(array_filter(explode(' ', trim($fullName))));
        if (empty($parts)) {
            return ['', ''];
        }

        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $lastName = array_pop($parts);
        $firstName = implode(' ', $parts);

        return [$firstName, $lastName];
    }

    private function extractTargetMonthFromDescription(string $description): ?Carbon
    {
        if ($description === '') {
            return null;
        }

        if (preg_match('/\b(0?[1-9]|1[0-2])[.\/-](\d{2,4})\b/u', $description, $matches) !== 1) {
            return null;
        }

        $month = (int) $matches[1];
        $yearPart = $matches[2];
        $year = strlen($yearPart) === 2 ? (2000 + (int) $yearPart) : (int) $yearPart;

        if ($year < 2000 || $year > 2100) {
            return null;
        }

        try {
            return Carbon::create($year, $month, 1)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
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
