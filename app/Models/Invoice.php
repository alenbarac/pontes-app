<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Services\SchoolYearService;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'workshop_id',
        'membership_plan_id',
        'amount_due',
        'amount_paid',
        'due_date',
        'payment_status',
        'reference_code',
        'notes',
        'school_year',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function workshop()
    {
        return $this->belongsTo(Workshop::class);
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /**
     * Generate a reference code for an invoice.
     * Format: YYYYMM-WWWCCC-NNN (3 parts for banking compatibility)
     * - YYYYMM: Year and month (e.g., 202501)
     * - WWWCCC: Workshop ID (3 digits) + Member ID (3 digits) combined (e.g., 001005)
     * - NNN: Sequence number for that member-workshop-month (e.g., 001)
     * 
     * @param int $memberId
     * @param int $workshopId
     * @param Carbon|string $dueDate
     * @return string
     */
    public static function generateReferenceCode(int $memberId, int $workshopId, Carbon|string $dueDate): string
    {
        if (is_string($dueDate)) {
            $dueDate = Carbon::parse($dueDate);
        }

        // Count existing invoices for this member-workshop-month combination to get sequence
        $existingCount = static::where('member_id', $memberId)
            ->where('workshop_id', $workshopId)
            ->whereYear('due_date', $dueDate->year)
            ->whereMonth('due_date', $dueDate->month)
            ->count();
        
        $sequence = str_pad($existingCount + 1, 3, '0', STR_PAD_LEFT);
        
        // Format: YYYYMM-WWWCCC-NNN (3 parts)
        $workshopMember = str_pad($workshopId, 3, '0', STR_PAD_LEFT) . 
                         str_pad($memberId, 3, '0', STR_PAD_LEFT);
        
        return $dueDate->format('Ym') . '-' . 
               $workshopMember . '-' . 
               $sequence;
    }

    /**
     * Get the school year attribute, auto-calculated from due_date if not set.
     * 
     * @return string|null
     */
    public function getSchoolYearAttribute($value): ?string
    {
        if ($value) {
            return $value;
        }

        // Auto-calculate from due_date if school_year is not set
        if ($this->due_date) {
            return SchoolYearService::getSchoolYearLabel(Carbon::parse($this->due_date));
        }

        return null;
    }

    /**
     * Set the school year attribute.
     * 
     * @param string|null $value
     * @return void
     */
    public function setSchoolYearAttribute($value): void
    {
        // If value is provided, use it; otherwise auto-calculate from due_date
        if ($value) {
            $this->attributes['school_year'] = $value;
        } elseif ($this->due_date) {
            $this->attributes['school_year'] = SchoolYearService::getSchoolYearLabel(Carbon::parse($this->due_date));
        }
    }

    /**
     * Scope a query to only include invoices for a specific school year.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $schoolYear School year label (e.g., "2025-2026")
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSchoolYear($query, string $schoolYear)
    {
        return $query->where('school_year', $schoolYear);
    }

    /**
     * Scope a query to only include invoices for a specific month.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon $month
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForMonth($query, Carbon $month)
    {
        return $query->whereYear('due_date', $month->year)
                     ->whereMonth('due_date', $month->month);
    }

    /**
     * Boot the model and auto-set school_year when due_date is set.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($invoice) {
            // Auto-set school_year from due_date if not explicitly set
            if ($invoice->due_date && !isset($invoice->attributes['school_year'])) {
                $invoice->school_year = SchoolYearService::getSchoolYearLabel(Carbon::parse($invoice->due_date));
            }
        });
    }
}
