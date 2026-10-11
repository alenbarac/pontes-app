<?php

namespace App\Support;

use Carbon\Carbon;

class PaymentMonth
{
    /**
     * Month written in a payment description, such as 10/2026 or 09-2026.
     */
    public static function fromDescription(string $description): ?Carbon
    {
        if ($description === '') {
            return null;
        }

        if (preg_match('/\b(0?[1-9]|1[0-2])\s*[.\/-]\s*(\d{2,4})\b/u', $description, $matches) !== 1) {
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
}
