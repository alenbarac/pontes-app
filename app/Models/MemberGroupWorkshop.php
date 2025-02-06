<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberGroupWorkshop extends Model
{
  use HasFactory;

  protected $table = 'member_workshop_group';

  protected $fillable = [
    'member_id',
    'workshop_id',
    'member_group_id',
  ];

  /**
   * A member is assigned to a group.
   */
  public function member()
  {
    return $this->belongsTo(Member::class);
  }

  /**
   * A workshop has groups.
   */
  public function workshop()
  {
    return $this->belongsTo(Workshop::class);
  }

  /**
   * A group belongs to a workshop.
   */
  public function group()
  {
    return $this->belongsTo(MemberGroup::class, 'member_group_id');
  }
}
