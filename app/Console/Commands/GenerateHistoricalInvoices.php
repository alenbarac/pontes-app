<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MemberWorkshop;
use App\Services\InvoiceGenerationService;
use Carbon\Carbon;

class GenerateHistoricalInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:generate-historical 
                            {--dry-run : Preview what would be generated without creating invoices}
                            {--from= : Start date (YYYY-MM-DD). Defaults to membership_start_date}
                            {--to= : End date (YYYY-MM-DD). Defaults to current month}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate historical invoices for all active members from their membership start dates to current month';

    /**
     * Execute the console command.
     */
    public function handle(InvoiceGenerationService $service): int
    {
        $dryRun = $this->option('dry-run');
        $fromDate = $this->option('from') 
            ? Carbon::parse($this->option('from')) 
            : null;
        $toDate = $this->option('to') 
            ? Carbon::parse($this->option('to'))->endOfMonth() 
            : Carbon::now()->endOfMonth();

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No invoices will be created');
            $this->newLine();
        }

        // Get all active member-workshop relationships
        $memberWorkshops = MemberWorkshop::with(['member', 'workshop', 'membershipPlan'])
            ->whereHas('member', function ($q) {
                $q->where('is_active', true);
            })
            ->whereNotNull('membership_plan_id')
            ->whereNotNull('membership_start_date')
            ->get();

        if ($memberWorkshops->isEmpty()) {
            $this->warn('No active member-workshop relationships found.');
            return Command::FAILURE;
        }

        $this->info("Found {$memberWorkshops->count()} active member-workshop relationships.");
        $this->newLine();

        $totalGenerated = 0;
        $totalSkipped = 0;
        $progressBar = $this->output->createProgressBar($memberWorkshops->count());
        $progressBar->start();

        foreach ($memberWorkshops as $memberWorkshop) {
            $startDate = $fromDate 
                ? $fromDate->copy() 
                : Carbon::parse($memberWorkshop->membership_start_date)->startOfMonth();

            // Don't generate beyond current month
            $actualEndDate = $toDate->copy();

            if ($dryRun) {
                // In dry-run, just count what would be generated
                $invoices = $service->generateHistoricalInvoices(
                    $memberWorkshop,
                    $startDate,
                    $actualEndDate
                );
                // Count would-be generated invoices
                $count = 0;
                $plan = $memberWorkshop->membershipPlan;
                if ($plan) {
                    $billingFrequency = strtolower($plan->billing_frequency);
                    $endDate = $memberWorkshop->membership_end_date 
                        ? Carbon::parse($memberWorkshop->membership_end_date) 
                        : $actualEndDate;
                    
                    $invoiceDates = $this->calculateInvoiceDates(
                        $startDate,
                        $endDate,
                        $billingFrequency,
                        $startDate,
                        $actualEndDate
                    );
                    
                    foreach ($invoiceDates as $invoiceDate) {
                        if (!$memberWorkshop->hasInvoiceForMonth($invoiceDate)) {
                            $count++;
                        }
                    }
                }
                $totalGenerated += $count;
            } else {
                $invoices = $service->generateHistoricalInvoices(
                    $memberWorkshop,
                    $startDate,
                    $actualEndDate
                );
                $generatedCount = $invoices->count();
                $totalGenerated += $generatedCount;
                
                // Debug: Log if no invoices generated for a member-workshop
                if ($generatedCount === 0 && $this->option('verbose')) {
                    $plan = $memberWorkshop->membershipPlan;
                    $this->line("  ⚠ No invoices for Member {$memberWorkshop->member_id}, Workshop {$memberWorkshop->workshop_id}, Plan: " . ($plan ? $plan->billing_frequency : 'none') . ", Start: {$memberWorkshop->membership_start_date}");
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("✅ Would generate {$totalGenerated} invoices");
        } else {
            $this->info("✅ Generated {$totalGenerated} invoices");
        }

        return Command::SUCCESS;
    }

    /**
     * Calculate invoice dates for preview (duplicate logic from service for dry-run).
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
        $actualStart = $current->gt($fromDate) ? $current : $fromDate->copy()->startOfMonth();
        $actualEnd = $endDate->lt($toDate) ? $endDate : $toDate->copy()->endOfMonth();

        switch ($billingFrequency) {
            case 'mjesečno':
            case 'monthly':
                $date = $actualStart->copy();
                while ($date->lte($actualEnd)) {
                    if ($date->gte($startDate) && (!$endDate || $date->lte($endDate))) {
                        $dates[] = $date->copy();
                    }
                    $date->addMonth();
                }
                break;

            case 'polugodišnje':
            case 'semi-annual':
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
                $date = $startDate->copy()->startOfMonth();
                while ($date->lte($actualEnd)) {
                    if ($date->gte($actualStart) && (!$endDate || $date->lte($endDate))) {
                        $dates[] = $date->copy();
                    }
                    $date->addYear();
                }
                break;

            default:
                $date = $actualStart->copy();
                while ($date->lte($actualEnd)) {
                    if ($date->gte($startDate) && (!$endDate || $date->lte($endDate))) {
                        $dates[] = $date->copy();
                    }
                    $date->addMonth();
                }
                break;
        }

        return $dates;
    }
}
