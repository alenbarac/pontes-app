<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'member_id',
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
     * The member associated with the membership.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The workshop associated with the membership.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function workshop()
    {
        return $this->belongsTo(Workshop::class);
    }

}
