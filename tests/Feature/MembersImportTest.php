<?php

use App\Imports\MembersImport;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopGroup;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->workshop = Workshop::factory()->create();
    $this->group = MemberGroup::create([
        'name' => 'Grupa 1',
        'description' => 'Test group',
    ]);
    WorkshopGroup::create([
        'workshop_id' => $this->workshop->id,
        'member_group_id' => $this->group->id,
    ]);
    MembershipPlan::create([
        'workshop_id' => $this->workshop->id,
        'plan' => 'Mjesečna članarina',
        'fee' => 40,
        'billing_frequency' => 'monthly',
        'total_fee' => 40,
    ]);
});

function membersCsv(array $rows): UploadedFile
{
    $header = ['GRUPA', 'IME I PREZIME', 'GODIŠTE', 'IME UPLATNICA', 'E-MAIL', 'ČLANARINA'];
    $lines = [implode(',', $header)];

    foreach ($rows as $row) {
        $lines[] = implode(',', array_map(function ($value) {
            $value = (string) $value;
            if (str_contains($value, ',') || str_contains($value, '"')) {
                return '"'.str_replace('"', '""', $value).'"';
            }

            return $value;
        }, $row));
    }

    return UploadedFile::fake()->createWithContent(
        'members.csv',
        implode("\n", $lines)
    );
}

test('import stores IME UPLATNICA as slip_payer_name on new members', function () {
    $file = membersCsv([
        ['Grupa 1', 'Dana Bagadur', '2012', 'Gordan Bagadur', 'satellite@net.hr', 'Mjesečna članarina'],
        ['Grupa 1', 'Paola Simonovic', '2011', '', 'paola@example.com', 'Mjesečna članarina'],
    ]);

    $import = new MembersImport;
    Excel::import($import, $file);

    $results = $import->getResults();
    expect($results['created_count'])->toBe(2)
        ->and($results['failed_count'])->toBe(0);

    $dana = Member::where('first_name', 'Dana')->where('last_name', 'Bagadur')->first();
    $paola = Member::where('first_name', 'Paola')->where('last_name', 'Simonovic')->first();

    expect($dana)->not->toBeNull()
        ->and($dana->slip_payer_name)->toBe('Gordan Bagadur')
        ->and($paola->slip_payer_name)->toBeNull();
});

test('import updates slip_payer_name on existing members without wiping empty cells', function () {
    $existingWithPayer = Member::factory()->create([
        'first_name' => 'Dana',
        'last_name' => 'Bagadur',
        'slip_payer_name' => null,
    ]);
    $existingKept = Member::factory()->create([
        'first_name' => 'Paola',
        'last_name' => 'Simonovic',
        'slip_payer_name' => 'Already Set',
    ]);

    $file = membersCsv([
        ['Grupa 1', 'Dana Bagadur', '2012', 'Gordan Bagadur', 'satellite@net.hr', 'Mjesečna članarina'],
        ['Grupa 1', 'Paola Simonovic', '2011', '', 'paola@example.com', 'Mjesečna članarina'],
    ]);

    $import = new MembersImport;
    Excel::import($import, $file);

    expect($import->getResults()['updated_count'])->toBe(2)
        ->and($existingWithPayer->fresh()->slip_payer_name)->toBe('Gordan Bagadur')
        ->and($existingKept->fresh()->slip_payer_name)->toBe('Already Set');
});

test('import strips a trailing asterisk from member names', function () {
    $file = membersCsv([
        ['Grupa 1', 'Iris Petrovic*', '2010', 'Ana Kukuljan', 'ana@example.com', 'Mjesečna članarina'],
    ]);

    Excel::import(new MembersImport, $file);

    $iris = Member::where('first_name', 'Iris')->where('last_name', 'Petrovic')->first();

    expect($iris)->not->toBeNull()
        ->and($iris->slip_payer_name)->toBe('Ana Kukuljan');
});

test('members import endpoint maps IME UPLATNICA from an uploaded spreadsheet', function () {
    $file = membersCsv([
        ['Grupa 1', 'Helena Jovanovic', '2012', 'Dijana Jovanovic', 'dijana@example.com', 'Mjesečna članarina'],
    ]);

    $this->post(route('members.import'), ['file' => $file])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Members/Import')
            ->where('importResult.created_count', 1)
            ->where('importResult.failed_count', 0)
        );

    $helena = Member::where('first_name', 'Helena')->where('last_name', 'Jovanovic')->first();

    expect($helena)->not->toBeNull()
        ->and($helena->slip_payer_name)->toBe('Dijana Jovanovic');
});

test('members import template includes the IME UPLATNICA column', function () {
    $response = $this->get(route('members.import.template'));

    $response->assertOk();
    expect($response->streamedContent())->toContain('IME UPLATNICA');
});

test('member import only reads the first six spreadsheet columns', function () {
    $import = new MembersImport;

    expect($import->endColumn())->toBe('F')
        ->and($import->readFilter()->readCell('A', 1))->toBeTrue()
        ->and($import->readFilter()->readCell('F', 1))->toBeTrue()
        ->and($import->readFilter()->readCell('G', 1))->toBeFalse()
        ->and($import->readFilter()->readCell('XFD', 1))->toBeFalse();
});
