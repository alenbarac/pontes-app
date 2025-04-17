<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberWorkshop extends Model
{
  use HasFactory;

  protected $table = 'member_workshop'; // Explicitly define the pivot table name

  protected $fillable = [
    'member_id',
    'workshop_id',
    'membership_plan_id',
    'membership_start_date',
    'membership_end_date'
  ];

  /**
   * A record links a member to a workshop.
   */
  public function member()
  {
    return $this->belongsTo(Member::class);
  }

  /**
   * A record links a workshop to a member.
   */
  public function workshop()
  {
    return $this->belongsTo(Workshop::class);
  }

  /**
   * A record includes a selected membership.
   */
  public function membershipPlan()
  {
    return $this->belongsTo(MembershipPlan::class);
  }
}
