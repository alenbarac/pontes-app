<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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

  /**
   * Get invoices for a specific period.
   * 
   * @param Carbon $from
   * @param Carbon $to
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getInvoicesForPeriod(Carbon $from, Carbon $to)
  {
    return Invoice::where('member_id', $this->member_id)
      ->where('workshop_id', $this->workshop_id)
      ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
      ->get();
  }

  /**
   * Check if an invoice already exists for a specific month.
   * 
   * @param Carbon $month
   * @return bool
   */
  public function hasInvoiceForMonth(Carbon $month): bool
  {
    return Invoice::where('member_id', $this->member_id)
      ->where('workshop_id', $this->workshop_id)
      ->whereYear('due_date', $month->year)
      ->whereMonth('due_date', $month->month)
      ->exists();
  }
}
