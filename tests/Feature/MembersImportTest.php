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

test('import replaces a stored email when the spreadsheet address differs', function () {
    $member = Member::factory()->create([
        'first_name' => 'Neva',
        'last_name' => 'Baretic',
        'email' => 'nevabaretic@import.local',
        'invoice_email' => null,
        'parent_email' => null,
    ]);

    $file = membersCsv([
        ['Grupa 1', 'Neva Baretic', '1950', '', 'neva.baretic@hotmail.com', 'Mjesečna članarina'],
    ]);

    $import = new MembersImport;
    Excel::import($import, $file);

    $member->refresh();

    expect($import->getResults()['updated_count'])->toBe(1)
        ->and($import->getResults()['failed_count'])->toBe(0)
        ->and($member->email)->toBe('neva.baretic@hotmail.com')
        ->and($member->invoice_email)->toBe('neva.baretic@hotmail.com')
        ->and($member->parent_email)->toBe('neva.baretic@hotmail.com');
});

test('import does not clear an email when the spreadsheet cell is empty', function () {
    $member = Member::factory()->create([
        'first_name' => 'Neva',
        'last_name' => 'Baretic',
        'email' => 'neva.baretic@hotmail.com',
        'invoice_email' => 'neva.baretic@hotmail.com',
        'parent_email' => 'neva.baretic@hotmail.com',
    ]);

    $file = membersCsv([
        ['Grupa 1', 'Neva Baretic', '1950', '', '', 'Mjesečna članarina'],
    ]);

    Excel::import(new MembersImport, $file);

    $member->refresh();

    expect($member->email)->toBe('neva.baretic@hotmail.com')
        ->and($member->invoice_email)->toBe('neva.baretic@hotmail.com');
});

test('import keeps a shared address on invoice fields when the login email is already taken', function () {
    Member::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Baretic',
        'email' => 'neva.baretic@hotmail.com',
    ]);
    $member = Member::factory()->create([
        'first_name' => 'Neva',
        'last_name' => 'Baretic',
        'email' => 'nevabaretic@import.local',
        'invoice_email' => null,
        'parent_email' => null,
    ]);

    $file = membersCsv([
        ['Grupa 1', 'Neva Baretic', '1950', '', 'neva.baretic@hotmail.com', 'Mjesečna članarina'],
    ]);

    Excel::import(new MembersImport, $file);

    $member->refresh();

    expect($member->email)->toBe('nevabaretic@import.local')
        ->and($member->invoice_email)->toBe('neva.baretic@hotmail.com')
        ->and($member->parent_email)->toBe('neva.baretic@hotmail.com');
});

test('import reads an E-MAIL ADRESA column', function () {
    $file = UploadedFile::fake()->createWithContent(
        'members.csv',
        implode("\n", [
            'GRUPA,IME I PREZIME,E-MAIL ADRESA,ČLANARINA',
            'Grupa 1,Neva Baretic,neva.baretic@hotmail.com,Mjesečna članarina',
        ])
    );

    $import = new MembersImport;
    Excel::import($import, $file);

    $neva = Member::where('first_name', 'Neva')->where('last_name', 'Baretic')->first();

    expect($import->getResults()['created_count'])->toBe(1)
        ->and($import->getResults()['failed_count'])->toBe(0)
        ->and($neva)->not->toBeNull()
        ->and($neva->email)->toBe('neva.baretic@hotmail.com')
        ->and($neva->invoice_email)->toBe('neva.baretic@hotmail.com');
});

test('member import only reads the first six spreadsheet columns', function () {
    $import = new MembersImport;

    expect($import->endColumn())->toBe('F')
        ->and($import->readFilter()->readCell('A', 1))->toBeTrue()
        ->and($import->readFilter()->readCell('F', 1))->toBeTrue()
        ->and($import->readFilter()->readCell('G', 1))->toBeFalse()
        ->and($import->readFilter()->readCell('XFD', 1))->toBeFalse();
});
