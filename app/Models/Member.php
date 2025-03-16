<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'date_of_birth', // Updated from birth_year to full date of birth
        'phone_number',
        'email',
        'is_active',
        'parent_contact',
        'parent_email',
    ];

    /**
     * A member can enroll in multiple workshops (Many-to-Many)
     */
    public function workshops()
    {
        return $this->belongsToMany(Workshop::class, 'member_workshop')->withPivot('membership_plan_id')
            ->withTimestamps();
    }

    /**
     * A member is assigned **one group per workshop** (One-to-Many)
     */
    public function workshopGroups()
    {
        return $this->hasMany(MemberGroupWorkshop::class);
    }

    /**
     * A member has **one or more memberships**, linked to workshops (One-to-Many)
     */
    public function memberships()
    {
        return $this->hasMany(MembershipPlan::class);
    }

    /**
     * A member has invoices (One-to-Many)
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
