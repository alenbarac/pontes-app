<?php

namespace App\Support;

use Illuminate\Support\Str;

class PayerNameMatcher
{
    public function score(string $payerName, string $memberName): int
    {
        $payerName = $this->normalize($payerName);
        $memberName = $this->normalize($memberName);

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

    /**
     * True when the member's name appears in the payment text.
     * The first name must match exactly. The last name may differ by one character,
     * which covers bank text that drops a diacritic (Perčić written as Pericic).
     */
    public function containsFullName(string $text, string $firstName, string $lastName): bool
    {
        $text = $this->normalize($text);
        $firstName = $this->normalize($firstName);
        $lastName = $this->normalize($lastName);

        if ($text === '' || strlen($firstName) < 3 || strlen($lastName) < 3) {
            return false;
        }

        if (! $this->containsWord($text, $firstName)) {
            return false;
        }

        if ($this->containsWord($text, $lastName)) {
            return true;
        }

        foreach (preg_split('/\s+/', $text) ?: [] as $word) {
            if (abs(strlen($word) - strlen($lastName)) > 1) {
                continue;
            }

            if (levenshtein($word, $lastName) <= 1) {
                return true;
            }
        }

        return false;
    }

    public function normalize(string $value): string
    {
        $normalized = Str::ascii(Str::lower(trim($value)));
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    private function containsWord(string $text, string $word): bool
    {
        return preg_match('/(?:^|\s)'.preg_quote($word, '/').'(?:\s|$)/', $text) === 1;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = array_values(array_filter(explode(' ', trim($fullName))));
        if ($parts === []) {
            return ['', ''];
        }

        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $lastName = array_pop($parts);

        return [implode(' ', $parts), $lastName];
    }
}
