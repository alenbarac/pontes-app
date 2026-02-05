<?php

namespace App\Services;

use Carbon\Carbon;

class SchoolYearService
{
    /**
     * Get the current school year period.
     * School year runs from September of previous year to June of current year.
     * 
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public static function getCurrentSchoolYear(): array
    {
        return self::getSchoolYearForDate(Carbon::now());
    }

    /**
     * Get the school year period for a given date.
     * School year format: September YYYY to June YYYY+1
     * 
     * @param Carbon $date
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public static function getSchoolYearForDate(Carbon $date): array
    {
        $year = $date->year;
        $month = $date->month;

        // If date is between September and December, school year started this year
        // If date is between January and June, school year started previous year
        if ($month >= 9) {
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
     * @param Carbon $date
     * @param int $schoolYearStart The starting year of the school year (e.g., 2025 for 2025-2026)
     * @return bool
     */
    public static function isDateInSchoolYear(Carbon $date, int $schoolYearStart): bool
    {
        $schoolYear = self::getSchoolYearForDate(Carbon::create($schoolYearStart, 9, 1));
        
        return $date->gte($schoolYear['start']) && $date->lte($schoolYear['end']);
    }

    /**
     * Get the school year label (e.g., "2025-2026") for a given date.
     * 
     * @param Carbon $date
     * @return string
     */
    public static function getSchoolYearLabel(Carbon $date): string
    {
        $schoolYear = self::getSchoolYearForDate($date);
        return $schoolYear['label'];
    }

    /**
     * Get all months in a school year period.
     * 
     * @param Carbon|null $date If null, uses current date
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
