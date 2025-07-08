<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'workshop_id'
    ];

    /**
     * A group is assigned per workshop.
     */
    public function workshop()
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * A group is assigned to members.
     */
    public function memberGroups()
    {
        return $this->hasMany(MemberGroupWorkshop::class);
    }

    public function members()
    {
        return $this->hasManyThrough(
            Member::class,
            MemberGroupWorkshop::class,
            'member_group_id', // Foreign key on MemberGroupWorkshop
            'id',              // Local key on Member
            'id',              // Local key on MemberGroup
            'member_id'        // Foreign key on MemberGroupWorkshop
        );
    }

    public function assignedWorkshop()
{
    return $this->hasOneThrough(
        Workshop::class,
        WorkshopGroup::class,
        'member_group_id', // FK on workshop_groups
        'id',              // PK on workshops
        'id',              // PK on member_groups
        'workshop_id'      // FK on workshop_groups
    );
}
}
