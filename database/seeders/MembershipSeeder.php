<?php
namespace Database\Seeders;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Workshop;
use Carbon\Carbon;
use Illuminate\Database\Seeder;


class MembershipSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $members = Member::all();
    $workshops = Workshop::all();

    if ($workshops->isEmpty()) {
      $this->command->error('No workshops found. Run WorkshopSeeder first.');
      return;
    }

    $membershipOptions = [
      [
        'plan' => 'Mjesečna clanarina',
        'fee' => 50.00,
        'billing_frequency' => 'Monthly',
        'discount_type' => null,
        'total_fee' => 50.00,
        'duration' => ['start' => Carbon::now()->subMonth(), 'end' => Carbon::now()->addMonths(11)],
      ],
      [
        'plan' => '6 mjeseci',
        'fee' => 270.00,
        'billing_frequency' => 'Semi-Annual',
        'discount_type' => 'Bulk Discount',
        'total_fee' => 270.00,
        'duration' => ['start' => Carbon::now()->subMonths(6), 'end' => Carbon::now()->addMonths(6)],
      ],
      [
        'plan' => 'Godišnja članarina',
        'fee' => 500.00,
        'billing_frequency' => 'Yearly',
        'discount_type' => 'Early Bird',
        'total_fee' => 450.00,
        'duration' => ['start' => Carbon::now()->startOfYear(), 'end' => Carbon::now()->endOfYear()],
      ],
    ];

    foreach ($members as $member) {
      // Ensure we loop through individual workshops
      $workshopsToAssign = $workshops->random(rand(1, 2))->all(); // Convert collection to array

      foreach ($workshopsToAssign as $workshop) {
        $membership = $membershipOptions[array_rand($membershipOptions)];

        Membership::create([
          'member_id' => $member->id,
          'workshop_id' => $workshop->id,
          'plan' => $membership['plan'],
          'fee' => $membership['fee'],
          'billing_frequency' => $membership['billing_frequency'],
          'discount_type' => $membership['discount_type'],
          'total_fee' => $membership['total_fee'],
          'start_date' => $membership['duration']['start'],
          'end_date' => $membership['duration']['end'],
          'status' => 'Active',
        ]);
      }
    }
  }
}
