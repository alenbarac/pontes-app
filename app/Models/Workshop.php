<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workshop extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
    ];

    /**
     * A workshop has many members through pivot table `member_workshop`.
     */
    public function members()
    {
        return $this->belongsToMany(Member::class, 'member_workshop')->withTimestamps();
    }

    /**
     * A workshop has multiple membership options.
     */
    public function memberships()
    {
        return $this->hasMany(MembershipPlan::class);
    }

    /**
     * A workshop has many groups through pivot table `workshop_groups`.
     */
    public function workshopGroups()
    {
        return $this->hasMany(WorkshopGroup::class);
    }

    /**
     * A workshop has many groups through pivot table `workshop_groups`.
     */
    public function groups()
    {
        return $this->hasManyThrough(
            MemberGroup::class,
            WorkshopGroup::class,
            'workshop_id', // Foreign key on workshop_groups
            'id',          // Local key on member_groups
            'id',          // Local key on workshops
            'member_group_id' // Foreign key on workshop_groups
        );
    }
}
