<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    public function groups()
    {
        return $this->belongsToMany(MemberGroup::class, 'member_group_member')->withTimestamps();
    }


    public function workshops()
    {
        return $this->belongsToMany(Workshop::class, 'member_workshop')->withTimestamps();
    }

}
