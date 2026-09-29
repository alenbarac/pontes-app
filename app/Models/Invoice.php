<?php

namespace App\Models;

use App\Services\SchoolYearService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'workshop_id',
        'membership_plan_id',
        'amount_due',
        'discount_percent',
        'original_amount',
        'amount_paid',
        'due_date',
        'payment_status',
        'reference_code',
        'notes',
        'slip_description',
        'school_year',
        'invoice_type',
        'session_date',
        'hours',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
        'discount_percent' => 'float',
        'original_amount' => 'float',
    ];

    protected $appends = [
        'has_discount',
        'discount_label',
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

    public function mailings()
    {
        return $this->hasMany(InvoiceMailing::class);
    }

    /**
     * Newest successfully emailed payment slip, if one exists.
     */
    public function latestSuccessfulSlipMailing()
    {
        return $this->hasOne(InvoiceMailing::class)->ofMany(
            ['sent_at' => 'max', 'id' => 'max'],
            function ($query) {
                $query->where('type', InvoiceMailing::TYPE_SLIP)
                    ->where('status', InvoiceMailing::STATUS_SENT);
            }
        );
    }

    /**
     * Newest slip attempt of any status (queued, sent, or failed).
     */
    public function latestSlipMailing()
    {
        return $this->hasOne(InvoiceMailing::class)->ofMany(
            ['id' => 'max'],
            function ($query) {
                $query->where('type', InvoiceMailing::TYPE_SLIP);
            }
        );
    }

    /**
     * Newest reminder attempt, when that mailing type exists.
     */
    public function latestReminderMailing()
    {
        return $this->hasOne(InvoiceMailing::class)->ofMany(
            ['id' => 'max'],
            function ($query) {
                $query->where('type', InvoiceMailing::TYPE_REMINDER);
            }
        );
    }

    /**
     * Latest slip attempt for Evidencija slanja. Resent is true only when
     * that latest attempt itself succeeded and an earlier slip did too.
     *
     * @return array{status: string, sent_at: string|null, sent_on: string|null, recipient: string|null, error: string|null, resent: bool}|null
     */
    public function staffSlipMailing(): ?array
    {
        $latest = $this->latestSlipMailing;

        if ($latest === null) {
            return null;
        }

        $resent = $latest->status === InvoiceMailing::STATUS_SENT
            && $this->successfulSlipMailingCount() > 1;

        return $latest->toStaffSummary($resent);
    }

    /**
     * @return array{status: string, sent_at: string|null, sent_on: string|null, recipient: string|null, error: string|null, resent: bool}|null
     */
    public function staffReminderMailing(): ?array
    {
        $latest = $this->latestReminderMailing;

        if ($latest === null) {
            return null;
        }

        return $latest->toStaffSummary(false);
    }

    private function successfulSlipMailingCount(): int
    {
        if (array_key_exists('successful_slip_mailings_count', $this->attributes)) {
            return (int) $this->attributes['successful_slip_mailings_count'];
        }

        return (int) $this->mailings()
            ->where('type', InvoiceMailing::TYPE_SLIP)
            ->where('status', InvoiceMailing::STATUS_SENT)
            ->count();
    }

    /**
     * Opis plaćanja (second line on the slip). Custom slip_description wins;
     * otherwise generated invoice notes, then Članarina.
     */
    public function slipPaymentNotes(): string
    {
        $override = trim((string) ($this->slip_description ?? ''));
        if ($override !== '') {
            return $override;
        }

        $notes = trim((string) ($this->notes ?? ''));

        return $notes !== '' ? $notes : 'Članarina';
    }

    /**
     * Default opis plaćanja stored on membership invoices (and printed on the slip).
     */
    public static function defaultMembershipNotes(?MembershipPlan $plan, Carbon|string $dueDate): string
    {
        if (is_string($dueDate)) {
            $dueDate = Carbon::parse($dueDate);
        }

        $schoolYear = str_replace('-', '/', SchoolYearService::getSchoolYearLabel($dueDate));

        if (! $plan) {
            return 'Članarina za '.$dueDate->format('m/Y');
        }

        $planName = mb_strtolower((string) $plan->plan);
        $frequency = mb_strtolower((string) $plan->billing_frequency);

        $isYearly = str_contains($planName, 'godišnja')
            || in_array($frequency, ['godišnje', 'yearly', 'annual'], true);

        if ($isYearly) {
            return 'Godišnja članarina '.$schoolYear;
        }

        $isSemiAnnual = str_contains($planName, '6 mjeseci')
            || in_array($frequency, ['polugodišnje', 'semi-annual', 'semiannual'], true);

        if ($isSemiAnnual) {
            $half = in_array($dueDate->month, [3, 4, 5, 6], true) ? 2 : 1;

            return 'Članarina 6 mjeseci - '.$schoolYear.' - '.$half.'/2';
        }

        return 'Članarina za '.$dueDate->format('m/Y');
    }

    /**
     * Generate a reference code for an invoice.
     * Format: YYYYMM-CCC-NNN (3 parts for banking compatibility)
     * - YYYYMM: Year and month (e.g., 202501)
     * - CCC: Member ID (3 digits, e.g., 005)
     * - NNN: Sequence number for that member-month (e.g., 001)
     *
     * Simplified format for easier manual entry (Poziv na broj) while maintaining
     * HUB3 compatibility (max 22 characters, current format is 14 characters).
     */
    public static function generateReferenceCode(int $memberId, Carbon|string $dueDate): string
    {
        if (is_string($dueDate)) {
            $dueDate = Carbon::parse($dueDate);
        }

        // Sequence is taken from reference codes that already use this prefix.
        // Counting member_id + due month misses rows whose member or due date
        // no longer matches the code (re-imported members, edited due dates),
        // and the unique index then rejects YYYYMM-CCC-001.
        $memberIdPadded = str_pad((string) $memberId, 3, '0', STR_PAD_LEFT);
        $prefix = $dueDate->format('Ym').'-'.$memberIdPadded.'-';

        $taken = static::query()
            ->where('reference_code', 'like', $prefix.'%')
            ->pluck('reference_code');

        $sequence = ($taken
            ->map(function (string $code) use ($prefix) {
                $suffix = substr($code, strlen($prefix));

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0) + 1;

        do {
            $referenceCode = $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while ($taken->contains($referenceCode));

        return $referenceCode;
    }

    /**
     * Get the school year attribute, auto-calculated from due_date if not set.
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
     * @param  string|null  $value
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
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $schoolYear  School year label (e.g., "2025-2026")
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSchoolYear($query, string $schoolYear)
    {
        return $query->where('school_year', $schoolYear);
    }

    /**
     * Scope a query to only include invoices for a specific month.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForMonth($query, Carbon $month)
    {
        return $query->whereYear('due_date', $month->year)
            ->whereMonth('due_date', $month->month);
    }

    /**
     * Apply or clear a percentage discount, recalculating amount_due from the original.
     */
    public function applyPercentageDiscount(?float $percent): void
    {
        $percent = $percent !== null ? round($percent, 2) : null;

        if ($percent === null || $percent <= 0) {
            if ($this->original_amount !== null) {
                $this->amount_due = $this->original_amount;
            }
            $this->discount_percent = null;
            $this->original_amount = null;

            return;
        }

        if ($percent >= 100) {
            return;
        }

        if ($this->original_amount === null) {
            $this->original_amount = (float) $this->amount_due;
        }

        $this->discount_percent = $percent;
        $this->amount_due = round((float) $this->original_amount * (1 - $percent / 100), 2);
    }

    public function getHasDiscountAttribute(): bool
    {
        if ($this->discount_percent !== null && (float) $this->discount_percent > 0) {
            return true;
        }

        return (bool) $this->membershipPlan?->isDiscounted();
    }

    public function getDiscountLabelAttribute(): ?string
    {
        if ($this->discount_percent !== null && (float) $this->discount_percent > 0) {
            return $this->formatDiscountLabel((float) $this->discount_percent);
        }

        if ($this->membershipPlan?->isDiscounted()) {
            $percent = $this->membershipPlan->discountPercent();

            return $this->membershipPlan->discount_type
                ?: ($percent ? $this->formatDiscountLabel($percent) : null);
        }

        return null;
    }

    /**
     * Invoices with a stored percentage discount or a discounted membership plan.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithDiscount($query)
    {
        return $query->where(function ($q) {
            $q->where('discount_percent', '>', 0)
                ->orWhereHas('membershipPlan', fn ($plan) => $plan->discounted());
        });
    }

    /**
     * Invoices without a percentage discount and not tied to a discounted plan.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithoutDiscount($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('discount_percent')
                ->orWhere('discount_percent', '<=', 0);
        })->whereDoesntHave('membershipPlan', fn ($plan) => $plan->discounted());
    }

    private function formatDiscountLabel(float $percent): string
    {
        $label = fmod($percent, 1.0) === 0.0
            ? (string) (int) $percent
            : rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');

        return $label.'% OFF';
    }

    /**
     * Scope a query to only include membership invoices.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMembership($query)
    {
        return $query->where('invoice_type', 'membership');
    }

    /**
     * Scope a query to only include session invoices.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSession($query)
    {
        return $query->where('invoice_type', 'session');
    }

    /**
     * Boot the model and auto-set school_year when due_date is set.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($invoice) {
            // Auto-set school_year from due_date if not explicitly set
            if ($invoice->due_date && ! isset($invoice->attributes['school_year'])) {
                $invoice->school_year = SchoolYearService::getSchoolYearLabel(Carbon::parse($invoice->due_date));
            }
        });
    }
}
