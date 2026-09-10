<?php

namespace App\Services;

use Carbon\Carbon;

class SchoolYearService
{
    /**
     * Get the current school year period.
     * School year runs from 1 September to 30 June; July–August count as the next year.
     *
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public static function getCurrentSchoolYear(): array
    {
        return self::getSchoolYearForDate(Carbon::now());
    }

    /**
     * Get the school year period for a given date.
     * School year runs from 1 September to 30 June.
     * July and August are the summer break and belong to the upcoming school year,
     * so generating Rujan invoices in August still shows on the dashboard.
     *
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public static function getSchoolYearForDate(Carbon $date): array
    {
        $year = $date->year;
        $month = $date->month;

        // September–June is the school year; July–August roll into the next one.
        if ($month >= 7) {
            $schoolYearStart = $year;
            $schoolYearEnd = $year + 1;
        } else {
            $schoolYearStart = $year - 1;
            $schoolYearEnd = $year;
        }

        $start = Carbon::create($schoolYearStart, 9, 1)->startOfDay();
        $end = Carbon::create($schoolYearEnd, 6, 30)->endOfDay();
        $label = "{$schoolYearStart}-{$schoolYearEnd}";

        return [
            'start' => $start,
            'end' => $end,
            'label' => $label,
        ];
    }

    /**
     * Check if a date falls within a specific school year.
     *
     * @param  int  $schoolYearStart  The starting year of the school year (e.g., 2025 for 2025-2026)
     */
    public static function isDateInSchoolYear(Carbon $date, int $schoolYearStart): bool
    {
        $schoolYear = self::getSchoolYearForDate(Carbon::create($schoolYearStart, 9, 1));

        return $date->gte($schoolYear['start']) && $date->lte($schoolYear['end']);
    }

    /**
     * Get the school year label (e.g., "2025-2026") for a given date.
     */
    public static function getSchoolYearLabel(Carbon $date): string
    {
        $schoolYear = self::getSchoolYearForDate($date);

        return $schoolYear['label'];
    }

    /**
     * Get all months in a school year period.
     *
     * @param  Carbon|null  $date  If null, uses current date
     * @return array Array of Carbon dates representing the first day of each month
     */
    public static function getMonthsInSchoolYear(?Carbon $date = null): array
    {
        $date = $date ?? Carbon::now();
        $schoolYear = self::getSchoolYearForDate($date);

        $months = [];
        $current = $schoolYear['start']->copy();

        while ($current->lte($schoolYear['end'])) {
            $months[] = $current->copy();
            $current->addMonth();
        }

        return $months;
    }
}
