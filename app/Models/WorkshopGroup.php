<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkshopGroup extends Model
{
    use HasFactory;

    protected $table = 'workshop_groups';

    protected $fillable = [
        'workshop_id',
        'member_group_id',
    ];

    public function memberGroup()
    {
        return $this->belongsTo(MemberGroup::class);
    }

    public function workshop()
    {
        return $this->belongsTo(Workshop::class);
    }
}
