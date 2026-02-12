<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MemberWorkshop;
use App\Models\Member;
use App\Models\Workshop;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class InvoiceGenerationService
{
    /**
     * Generate invoices for a specific month.
     * 
     * @param Carbon $targetMonth The month to generate invoices for (first day of month)
     * @param array|null $memberIds Optional array of member IDs to limit generation
     * @return array{generated: int, skipped: int, errors: array}
     */
    public function generateInvoicesForMonth(Carbon $targetMonth, ?array $memberIds = null): array
    {
        $targetMonth = $targetMonth->copy()->startOfMonth();
        $generated = 0;
        $skipped = 0;
        $errors = [];

        // Get all active member-workshop relationships
        // Exclude individual counseling workshops (type = 'Individualno')
        $query = MemberWorkshop::with(['member', 'workshop', 'membershipPlan'])
            ->whereHas('member', function ($q) {
                $q->where('is_active', true);
            })
            ->whereHas('workshop', function ($q) {
                $q->where('type', '!=', 'Individualno');
            })
            ->whereNotNull('membership_plan_id')
            ->whereNotNull('membership_start_date');

        if ($memberIds) {
            $query->whereIn('member_id', $memberIds);
        }

        $memberWorkshops = $query->get();

        foreach ($memberWorkshops as $memberWorkshop) {
            try {
                if ($this->shouldGenerateInvoice($memberWorkshop, $targetMonth)) {
                    $invoice = $this->createInvoice($memberWorkshop, $targetMonth);
                    if ($invoice) {
                        $generated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'member_id' => $memberWorkshop->member_id,
                    'workshop_id' => $memberWorkshop->workshop_id,
                    'error' => $e->getMessage(),
                ];
                Log::error('Invoice generation error', [
                    'member_workshop_id' => $memberWorkshop->id,
                    'target_month' => $targetMonth->format('Y-m'),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Generate historical invoices for a member-workshop from a date range.
     * 
     * @param MemberWorkshop $memberWorkshop
     * @param Carbon $fromDate
     * @param Carbon $toDate
     * @return Collection Collection of created Invoice models
     */
    public function generateHistoricalInvoices(
        MemberWorkshop $memberWorkshop,
        Carbon $fromDate,
        Carbon $toDate
    ): Collection {
        $invoices = collect();
        $plan = $memberWorkshop->membershipPlan;

        if (!$plan) {
            return $invoices;
        }

        $billingFrequency = strtolower($plan->billing_frequency);
        $startDate = $memberWorkshop->membership_start_date 
            ? Carbon::parse($memberWorkshop->membership_start_date) 
            : $fromDate;
        
        $endDate = $memberWorkshop->membership_end_date 
            ? Carbon::parse($memberWorkshop->membership_end_date) 
            : $toDate;

        // Determine invoice dates based on billing frequency
        $invoiceDates = $this->calculateInvoiceDates($startDate, $endDate, $billingFrequency, $fromDate, $toDate);

        foreach ($invoiceDates as $invoiceDate) {
            // Skip if invoice already exists
            if ($memberWorkshop->hasInvoiceForMonth($invoiceDate)) {
                continue;
            }

            // Check if date is within membership period
            // Compare month-to-month (invoice dates are always start of month)
            $startDateMonth = $startDate->copy()->startOfMonth();
            $endDateMonth = $endDate->copy()->endOfMonth();
            if ($invoiceDate->lt($startDateMonth) || ($memberWorkshop->membership_end_date && $invoiceDate->gt($endDateMonth))) {
                continue;
            }

            $invoice = $this->createInvoice($memberWorkshop, $invoiceDate);
            if ($invoice) {
                $invoices->push($invoice);
            }
        }

        return $invoices;
    }

    /**
     * Check if an invoice should be generated for a member-workshop and target date.
     * 
     * @param MemberWorkshop $memberWorkshop
     * @param Carbon $targetDate
     * @return bool
     */
    public function shouldGenerateInvoice(MemberWorkshop $memberWorkshop, Carbon $targetDate): bool
    {
        // Check if member is active
        if (!$memberWorkshop->member || !$memberWorkshop->member->is_active) {
            return false;
        }

        // Check if membership plan exists
        if (!$memberWorkshop->membershipPlan) {
            return false;
        }

        // Check if target date is within membership period
        if ($memberWorkshop->membership_start_date) {
            $startDate = Carbon::parse($memberWorkshop->membership_start_date)->startOfMonth();
            if ($targetDate->lt($startDate)) {
                return false;
            }
        }

        if ($memberWorkshop->membership_end_date) {
            $endDate = Carbon::parse($memberWorkshop->membership_end_date)->endOfMonth();
            if ($targetDate->gt($endDate)) {
                return false;
            }
        }

        // Check if invoice already exists for this month
        if ($memberWorkshop->hasInvoiceForMonth($targetDate)) {
            return false;
        }

        // Check if billing frequency matches
        $billingFrequency = strtolower($memberWorkshop->membershipPlan->billing_frequency);
        $startDate = $memberWorkshop->membership_start_date 
            ? Carbon::parse($memberWorkshop->membership_start_date)->startOfMonth() 
            : $targetDate;

        return $this->isBillingDate($targetDate, $startDate, $billingFrequency);
    }

    /**
     * Calculate invoice amount based on plan and target date.
     * 
     * @param \App\Models\MembershipPlan $plan
     * @param Carbon $targetDate
     * @return float
     */
    public function calculateInvoiceAmount($plan, Carbon $targetDate): float
    {
        // For now, use the plan's fee. Can be extended for prorating if needed.
        return (float) ($plan->total_fee ?? $plan->fee);
    }

    /**
     * Create an invoice for a member-workshop and target month.
     * 
     * @param MemberWorkshop $memberWorkshop
     * @param Carbon $targetMonth
     * @return Invoice|null
     */
    public function createInvoice(MemberWorkshop $memberWorkshop, Carbon $targetMonth): ?Invoice
    {
        $plan = $memberWorkshop->membershipPlan;
        if (!$plan) {
            return null;
        }

        // Use the first day of the target month as due date
        $dueDate = $targetMonth->copy()->startOfMonth();

        // Generate reference code
        $referenceCode = Invoice::generateReferenceCode(
            $memberWorkshop->member_id,
            $dueDate
        );

        // Calculate amount
        $amount = $this->calculateInvoiceAmount($plan, $dueDate);

        // Get school year
        $schoolYear = SchoolYearService::getSchoolYearLabel($dueDate);

        // Create invoice
        $invoice = Invoice::create([
            'member_id' => $memberWorkshop->member_id,
            'workshop_id' => $memberWorkshop->workshop_id,
            'membership_plan_id' => $plan->id,
            'amount_due' => $amount,
            'amount_paid' => 0,
            'due_date' => $dueDate->toDateString(),
            'payment_status' => 'Otvoreno',
            'reference_code' => $referenceCode,
            'school_year' => $schoolYear,
            'notes' => 'Članarina za ' . $dueDate->format('m/Y'),
        ]);

        return $invoice;
    }

    /**
     * Calculate invoice dates based on billing frequency.
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param string $billingFrequency
     * @param Carbon $fromDate
     * @param Carbon $toDate
     * @return array Array of Carbon dates
     */
    protected function calculateInvoiceDates(
        Carbon $startDate,
        Carbon $endDate,
        string $billingFrequency,
        Carbon $fromDate,
        Carbon $toDate
    ): array {
        $dates = [];
        $current = $startDate->copy()->startOfMonth();

        // Limit to the requested date range
        $actualStart = $current->gt($fromDate) ? $current : $fromDate->copy()->startOfMonth();
        $actualEnd = $endDate->lt($toDate) ? $endDate : $toDate->copy()->endOfMonth();

        switch ($billingFrequency) {
            case 'mjesečno':
            case 'monthly':
                // Monthly: every month from start date
                $date = $actualStart->copy();
                $startDateMonth = $startDate->copy()->startOfMonth();
                $endDateMonth = $endDate ? $endDate->copy()->endOfMonth() : null;
                while ($date->lte($actualEnd)) {
                    if ($date->gte($startDateMonth) && (!$endDateMonth || $date->lte($endDateMonth))) {
                        $dates[] = $date->copy();
                    }
                    $date->addMonth();
                }
                break;

            case 'polugodišnje':
            case 'semi-annual':
                // Semi-annual: every 6 months from start date
                $date = $startDate->copy()->startOfMonth();
                while ($date->lte($actualEnd)) {
                    if ($date->gte($actualStart) && (!$endDate || $date->lte($endDate))) {
                        $dates[] = $date->copy();
                    }
                    $date->addMonths(6);
                }
                break;

            case 'godišnje':
            case 'yearly':
            case 'annual':
                // Annual: once per year from start date
                $date = $startDate->copy()->startOfMonth();
                while ($date->lte($actualEnd)) {
                    if ($date->gte($actualStart) && (!$endDate || $date->lte($endDate))) {
                        $dates[] = $date->copy();
                    }
                    $date->addYear();
                }
                break;

            default:
                // Default: monthly
                $date = $actualStart->copy();
                $startDateMonth = $startDate->copy()->startOfMonth();
                $endDateMonth = $endDate ? $endDate->copy()->endOfMonth() : null;
                while ($date->lte($actualEnd)) {
                    if ($date->gte($startDateMonth) && (!$endDateMonth || $date->lte($endDateMonth))) {
                        $dates[] = $date->copy();
                    }
                    $date->addMonth();
                }
                break;
        }

        return $dates;
    }

    /**
     * Check if a target date matches the billing frequency from start date.
     * 
     * @param Carbon $targetDate
     * @param Carbon $startDate
     * @param string $billingFrequency
     * @return bool
     */
    protected function isBillingDate(Carbon $targetDate, Carbon $startDate, string $billingFrequency): bool
    {
        $targetMonth = $targetDate->copy()->startOfMonth();
        $startMonth = $startDate->copy()->startOfMonth();

        if ($targetMonth->lt($startMonth)) {
            return false;
        }

        switch ($billingFrequency) {
            case 'mjesečno':
            case 'monthly':
                // Monthly: every month
                return true;

            case 'polugodišnje':
            case 'semi-annual':
                // Semi-annual: every 6 months
                $monthsDiff = $startMonth->diffInMonths($targetMonth);
                return $monthsDiff % 6 === 0;

            case 'godišnje':
            case 'yearly':
            case 'annual':
                // Annual: same month each year
                return $targetMonth->month === $startMonth->month && 
                       $targetMonth->year >= $startMonth->year;

            default:
                // Default: monthly
                return true;
        }
    }

    /**
     * Preview invoices that would be generated for a target month.
     * 
     * @param Carbon $targetMonth
     * @param array|null $memberIds
     * @return Collection Collection of preview data
     */
    public function previewInvoicesForMonth(Carbon $targetMonth, ?array $memberIds = null): Collection
    {
        $targetMonth = $targetMonth->copy()->startOfMonth();
        $previews = collect();

        // Exclude individual counseling workshops from preview
        $query = MemberWorkshop::with(['member', 'workshop', 'membershipPlan'])
            ->whereHas('member', function ($q) {
                $q->where('is_active', true);
            })
            ->whereHas('workshop', function ($q) {
                $q->where('type', '!=', 'Individualno');
            })
            ->whereNotNull('membership_plan_id')
            ->whereNotNull('membership_start_date');

        if ($memberIds) {
            $query->whereIn('member_id', $memberIds);
        }

        $memberWorkshops = $query->get();

        foreach ($memberWorkshops as $memberWorkshop) {
            $shouldGenerate = $this->shouldGenerateInvoice($memberWorkshop, $targetMonth);
            $alreadyExists = $memberWorkshop->hasInvoiceForMonth($targetMonth);

            $amount = 0;
            if ($memberWorkshop->membershipPlan) {
                $amount = (float) ($memberWorkshop->membershipPlan->total_fee ?? $memberWorkshop->membershipPlan->fee ?? 0);
            }

            $previews->push([
                'member_workshop_id' => $memberWorkshop->id,
                'member_id' => $memberWorkshop->member_id,
                'member_name' => $memberWorkshop->member->first_name . ' ' . $memberWorkshop->member->last_name,
                'workshop_id' => $memberWorkshop->workshop_id,
                'workshop_name' => $memberWorkshop->workshop->name,
                'membership_plan' => $memberWorkshop->membershipPlan->plan ?? null,
                'amount' => $amount,
                'should_generate' => $shouldGenerate,
                'already_exists' => $alreadyExists,
                'reason' => $alreadyExists ? 'Invoice already exists' : 
                          (!$shouldGenerate ? 'Does not match billing frequency or date range' : 'Ready to generate'),
            ]);
        }

        return $previews;
    }

    /**
     * Calculate default session amount based on member's dramska radionica status.
     * 
     * @param Member $member
     * @param Workshop $workshop
     * @return float
     */
    public function calculateSessionAmount(Member $member, Workshop $workshop): float
    {
        // Check if member has dramska radionica workshop
        $hasDramskaRadionica = $member->workshops()
            ->whereHas('workshop', function ($q) {
                $q->where('name', 'Dramska radionica')
                  ->orWhere('type', 'Groupe');
            })
            ->exists();

        // 50 EUR if member has dramska radionica, 60 EUR otherwise
        return $hasDramskaRadionica ? 50.00 : 60.00;
    }

    /**
     * Generate a session invoice for individual counseling.
     * 
     * @param Member $member
     * @param Workshop $workshop
     * @param Carbon $sessionDate
     * @param float $amount
     * @param string|null $notes
     * @return Invoice
     */
    public function generateSessionInvoice(
        Member $member,
        Workshop $workshop,
        Carbon $sessionDate,
        float $amount,
        ?string $notes = null
    ): Invoice {
        // Use session date as due date
        $dueDate = $sessionDate->copy();

        // Generate reference code
        $referenceCode = Invoice::generateReferenceCode(
            $member->id,
            $dueDate
        );

        // Get school year
        $schoolYear = SchoolYearService::getSchoolYearLabel($dueDate);

        // Get membership plan for this workshop (should be "Po sastanku")
        $membershipPlan = $member->workshops()
            ->where('workshop_id', $workshop->id)
            ->first()
            ?->membershipPlan;

        // Create session invoice
        $invoice = Invoice::create([
            'member_id' => $member->id,
            'workshop_id' => $workshop->id,
            'membership_plan_id' => $membershipPlan?->id,
            'amount_due' => $amount,
            'amount_paid' => 0,
            'due_date' => $dueDate->toDateString(),
            'payment_status' => 'Otvoreno',
            'reference_code' => $referenceCode,
            'school_year' => $schoolYear,
            'invoice_type' => 'session',
            'session_date' => $sessionDate->toDateString(),
            'notes' => $notes ?? 'Individualno savjetovanje - ' . $sessionDate->format('d.m.Y'),
        ]);

        return $invoice;
    }
}
