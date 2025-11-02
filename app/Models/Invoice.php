<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Invoice extends Model
{
    use HasFactory;

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function workshop()
    {
        return $this->belongsTo(Workshop::class);
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /**
     * Generate a reference code for an invoice.
     * Format: YYYYMM-WWWCCC-NNN (3 parts for banking compatibility)
     * - YYYYMM: Year and month (e.g., 202501)
     * - WWWCCC: Workshop ID (3 digits) + Member ID (3 digits) combined (e.g., 001005)
     * - NNN: Sequence number for that member-workshop-month (e.g., 001)
     * 
     * @param int $memberId
     * @param int $workshopId
     * @param Carbon|string $dueDate
     * @return string
     */
    public static function generateReferenceCode(int $memberId, int $workshopId, Carbon|string $dueDate): string
    {
        if (is_string($dueDate)) {
            $dueDate = Carbon::parse($dueDate);
        }

        // Count existing invoices for this member-workshop-month combination to get sequence
        $existingCount = static::where('member_id', $memberId)
            ->where('workshop_id', $workshopId)
            ->whereYear('due_date', $dueDate->year)
            ->whereMonth('due_date', $dueDate->month)
            ->count();
        
        $sequence = str_pad($existingCount + 1, 3, '0', STR_PAD_LEFT);
        
        // Format: YYYYMM-WWWCCC-NNN (3 parts)
        $workshopMember = str_pad($workshopId, 3, '0', STR_PAD_LEFT) . 
                         str_pad($memberId, 3, '0', STR_PAD_LEFT);
        
        return $dueDate->format('Ym') . '-' . 
               $workshopMember . '-' . 
               $sequence;
    }
}
