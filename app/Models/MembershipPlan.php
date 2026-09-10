<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'workshop_id',
        'plan',
        'fee',
        'billing_frequency',
        'discount_type',
        'total_fee',
        'start_date',
        'end_date',
        'status',
    ];

    /**
     * A membership belongs to a workshop.
     */
    public function workshop()
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * Whether this plan is a percentage-off variant (e.g. 20% OFF).
     */
    public function isDiscounted(): bool
    {
        return (bool) preg_match('/%\s*off/i', (string) $this->plan)
            || (bool) preg_match('/^\d+(\.\d+)?%\s*OFF$/i', (string) $this->discount_type);
    }

    /**
     * Parsed percentage off, if this plan is a discount variant.
     */
    public function discountPercent(): ?float
    {
        foreach ([(string) $this->discount_type, (string) $this->plan] as $source) {
            if (preg_match('/(\d+(?:\.\d+)?)\s*%\s*OFF/i', $source, $matches)) {
                return (float) $matches[1];
            }
        }

        return null;
    }

    /**
     * Amount charged before the percentage discount.
     */
    public function amountBeforeDiscount(?float $discountedAmount = null): float
    {
        $discounted = $discountedAmount ?? (float) ($this->total_fee ?? $this->fee);
        $percent = $this->discountPercent();

        if ($percent && $percent > 0 && $percent < 100) {
            return round($discounted / (1 - $percent / 100), 2);
        }

        return $discounted;
    }

    /**
     * Snapshot stored on invoices generated from this plan.
     *
     * @return array{discount_percent: float|null, original_amount: float|null}
     */
    public function invoiceDiscountSnapshot(float $amountDue): array
    {
        $percent = $this->discountPercent();

        if (! $this->isDiscounted() || ! $percent) {
            return [
                'discount_percent' => null,
                'original_amount' => null,
            ];
        }

        return [
            'discount_percent' => $percent,
            'original_amount' => $this->amountBeforeDiscount($amountDue),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDiscounted($query)
    {
        return $query->where(function ($q) {
            $q->where('plan', 'like', '%OFF%')
                ->orWhere('discount_type', 'like', '%OFF%');
        });
    }

    /**
     * A membership is assigned via a member's enrollment in a workshop.
     */
    public function memberWorkshop()
    {
        return $this->hasMany(MemberWorkshop::class);
    }
}
