<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'last_name',
        'birth_year',
        'phone_number',
        'email',
        'is_active',
        'parent_contact',
        'parent_email',
        'membership_id'
    ];

    public function groups()
    {
        return $this->belongsToMany(MemberGroup::class, 'member_group_member')->withTimestamps();
    }

    public function workshops()
    {
        return $this->belongsToMany(Workshop::class, 'member_workshop')->withTimestamps();
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

}
