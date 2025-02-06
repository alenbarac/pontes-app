<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    use HasFactory;

    protected $fillable = [
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
     * A membership is assigned via a member's enrollment in a workshop.
     */
    public function memberWorkshop()
    {
        return $this->hasMany(MemberWorkshop::class);
    }
}
