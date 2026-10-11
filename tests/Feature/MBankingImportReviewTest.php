<?php

use App\Models\BankStatementImport;
use App\Models\BankStatementImportLine;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function statementCsv(array $rows): UploadedFile
{
    $lines = [
        'Redni broj;Datum valute;Uplate;PNB primatelja;Platitelj/primatelj;Opis placanja tecaj',
    ];

    foreach ($rows as $index => $row) {
        $lines[] = implode(';', [
            $index + 1,
            $row['date'] ?? '15.9.2026.',
            $row['amount'],
            $row['reference'] ?? '',
            $row['payer'] ?? '',
            $row['description'] ?? 'Clanarina',
        ]);
    }

    return UploadedFile::fake()->createWithContent(
        'izvod.csv',
        implode("\n", $lines)
    );
}

function statementPlan(Workshop $workshop, string $name, float $fee): MembershipPlan
{
    return MembershipPlan::create([
        'workshop_id' => $workshop->id,
        'plan' => $name,
        'fee' => $fee,
        'billing_frequency' => 'mjesecno',
        'total_fee' => $fee,
    ]);
}

test('import saves a review that is still there after refresh', function () {
    $workshop = Workshop::factory()->create();
    $member = Member::factory()->create([
        'first_name' => 'Ivana',
        'last_name' => 'Dujmic',
    ]);
    $oldPlan = statementPlan($workshop, 'Mjesecna 50', 50);
    $newPlan = statementPlan($workshop, 'Mjesecna 225', 225);
    $member->workshops()->attach($workshop->id, [
        'membership_plan_id' => $oldPlan->id,
        'membership_start_date' => '2026-01-01',
    ]);

    $paidInvoice = Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $oldPlan->id,
        'amount_due' => 50,
        'payment_status' => 'Otvoreno',
        'due_date' => '2026-09-15',
        'reference_code' => '202609-001-001',
    ]);
    $mismatchInvoice = Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $oldPlan->id,
        'amount_due' => 50,
        'payment_status' => 'Otvoreno',
        'due_date' => '2026-09-15',
        'reference_code' => '202609-049-001',
    ]);
    $openInvoice = Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $oldPlan->id,
        'amount_due' => 40,
        'payment_status' => 'Otvoreno',
        'due_date' => '2026-09-15',
        'reference_code' => '202609-099-001',
    ]);

    $this->post(route('invoices.importMbanking.store'), [
        'file' => statementCsv([
            ['amount' => '50,00', 'reference' => '202609-001-001', 'payer' => 'IVANA DUJMIC'],
            ['amount' => '225,00', 'reference' => '202609-049-001', 'payer' => 'IVANA DUJMIC'],
            ['amount' => '80,00', 'reference' => '', 'payer' => 'IVANA DUJMIC'],
            ['amount' => '0,00', 'reference' => '', 'payer' => 'ISPLATA'],
        ]),
    ])->assertRedirect();

    $import = BankStatementImport::first();
    expect($import)->not->toBeNull()
        ->and($import->paid_count)->toBe(1)
        ->and($import->mismatch_count)->toBe(1)
        ->and($import->unresolved_count)->toBe(1)
        ->and($import->skipped_count)->toBe(1)
        ->and($import->lines()->where('kind', 'paid')->count())->toBe(1)
        ->and($import->lines()->where('kind', 'skipped')->count())->toBe(0);

    $paidInvoice->refresh();
    $mismatchInvoice->refresh();
    expect($paidInvoice->payment_status)->toBe('Plaćeno')
        ->and($mismatchInvoice->payment_status)->toBe('Neusklađeno')
        ->and((float) $mismatchInvoice->amount_paid)->toBe(225.0);

    $this->get(route('invoices.importMbanking.show', $import))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/ImportMBankingReview')
            ->where('statementImport.filename', 'izvod.csv')
            ->has('mismatches', 1)
            ->where('mismatches.0.member_id', $member->id)
            ->where('mismatches.0.amount_paid', 225)
            ->where('mismatches.0.current_plan.id', $oldPlan->id)
            ->where('mismatches.0.matching_plans.0.id', $newPlan->id)
            ->has('unresolved', 1)
            ->where('unresolved.0.payer', 'IVANA DUJMIC')
            ->where('unresolved.0.amount_paid', 80)
            ->where('unresolved.0.suggestions.0.invoice_id', $openInvoice->id)
        );

    $this->get(route('invoices.importMbanking.show', $import))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('mismatches', 1));

    $this->get(route('invoices.importMbanking.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/ImportMBanking')
            ->where('latestImport.id', $import->id)
            ->where('latestImport.open_mismatch_count', 1)
            ->where('latestImport.open_unresolved_count', 1)
        );
});

test('aligning a mismatch updates only that invoice', function () {
    [$import, $line, $invoice, $member, $oldPlan] = mismatchImport();

    $this->post(route('invoices.importMbanking.lines.align', [$import, $line]))
        ->assertRedirect(route('invoices.importMbanking.show', $import));

    $invoice->refresh();
    $line->refresh();
    $member->refresh();

    expect($invoice->payment_status)->toBe('Plaćeno')
        ->and((float) $invoice->amount_due)->toBe(225.0)
        ->and((float) $invoice->amount_paid)->toBe(225.0)
        ->and($invoice->membership_plan_id)->toBe($oldPlan->id)
        ->and($line->resolution)->toBe('resolved')
        ->and($member->workshops()->first()->pivot->membership_plan_id)->toBe($oldPlan->id);

    $this->get(route('invoices.importMbanking.show', $import))
        ->assertInertia(fn (Assert $page) => $page
            ->has('mismatches', 0)
            ->has('resolved', 1)
            ->where('resolved.0.resolution', 'resolved')
        );
});

test('applying a matching plan updates the enrollment and the invoice', function () {
    [$import, $line, $invoice, $member, $oldPlan, $newPlan, $workshop] = mismatchImport();
    $wrongPlan = statementPlan($workshop, 'Pogresan', 999);

    $this->post(route('invoices.importMbanking.lines.applyPlan', [$import, $line]), [
        'membership_plan_id' => $wrongPlan->id,
    ])->assertSessionHasErrors('membership_plan_id');

    $invoice->refresh();
    expect($invoice->payment_status)->toBe('Neusklađeno')
        ->and((float) $invoice->amount_due)->toBe(50.0);

    $this->post(route('invoices.importMbanking.lines.applyPlan', [$import, $line]), [
        'membership_plan_id' => $newPlan->id,
    ])->assertRedirect(route('invoices.importMbanking.show', $import));

    $invoice->refresh();
    $line->refresh();

    expect($invoice->payment_status)->toBe('Plaćeno')
        ->and((float) $invoice->amount_due)->toBe(225.0)
        ->and($invoice->membership_plan_id)->toBe($newPlan->id)
        ->and($line->resolution)->toBe('resolved')
        ->and((int) $member->workshops()->first()->pivot->membership_plan_id)->toBe($newPlan->id)
        ->and((int) $member->workshops()->first()->pivot->membership_plan_id)->not->toBe($oldPlan->id);
});

test('an unresolved payment can be attached or dismissed', function () {
    $workshop = Workshop::factory()->create();
    $member = Member::factory()->create([
        'first_name' => 'Ivana',
        'last_name' => 'Dujmic',
    ]);
    $different = Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 50,
        'payment_status' => 'Otvoreno',
        'due_date' => '2026-09-15',
        'reference_code' => '202609-010-001',
    ]);
    $exact = Invoice::factory()->create([
        'member_id' => Member::factory()->create([
            'first_name' => 'Netko',
            'last_name' => 'Drugi',
        ])->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 80,
        'payment_status' => 'Otvoreno',
        'due_date' => '2026-09-15',
        'reference_code' => '202609-011-001',
    ]);
    $dismissInvoice = Invoice::factory()->create([
        'member_id' => Member::factory()->create([
            'first_name' => 'Lea',
            'last_name' => 'Novak',
        ])->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 15,
        'payment_status' => 'Otvoreno',
        'due_date' => '2026-09-15',
        'reference_code' => '202609-012-001',
    ]);

    $this->post(route('invoices.importMbanking.store'), [
        'file' => statementCsv([
            ['amount' => '225,00', 'payer' => 'IVANA DUJMIC', 'reference' => ''],
            ['amount' => '80,00', 'payer' => 'TVRTKA DOO', 'reference' => ''],
            ['amount' => '12,00', 'payer' => 'BANKA NAKNADA', 'reference' => ''],
        ]),
    ])->assertRedirect();

    $import = BankStatementImport::first();
    $differentLine = $import->lines()->where('amount_paid', 225)->first();
    $exactLine = $import->lines()->where('amount_paid', 80)->first();
    $dismissLine = $import->lines()->where('amount_paid', 12)->first();

    $this->get(route('invoices.importMbanking.show', $import))
        ->assertInertia(fn (Assert $page) => $page
            ->has('unresolved', 3)
            ->where('unresolved.0.suggestions.0.invoice_id', $different->id)
        );

    $this->post(route('invoices.importMbanking.lines.attach', [$import, $differentLine]), [
        'invoice_id' => $different->id,
    ])->assertRedirect();

    $different->refresh();
    $differentLine->refresh();
    expect($different->payment_status)->toBe('Neusklađeno')
        ->and((float) $different->amount_due)->toBe(50.0)
        ->and((float) $different->amount_paid)->toBe(225.0)
        ->and($differentLine->kind)->toBe('mismatch')
        ->and($differentLine->resolution)->toBe('open');

    $this->post(route('invoices.importMbanking.lines.attach', [$import, $exactLine]), [
        'invoice_id' => $exact->id,
    ])->assertRedirect();

    $exact->refresh();
    $exactLine->refresh();
    expect($exact->payment_status)->toBe('Plaćeno')
        ->and((float) $exact->amount_paid)->toBe(80.0)
        ->and($exactLine->resolution)->toBe('resolved');

    $this->post(route('invoices.importMbanking.lines.dismiss', [$import, $dismissLine]))
        ->assertRedirect();

    $dismissInvoice->refresh();
    $dismissLine->refresh();
    expect($dismissInvoice->payment_status)->toBe('Otvoreno')
        ->and((float) $dismissInvoice->amount_paid)->toBe(0.0)
        ->and($dismissLine->resolution)->toBe('dismissed');
});

test('an ambiguous row can only be attached to one of the candidate invoices', function () {
    $workshop = Workshop::factory()->create();
    $first = Member::factory()->create(['first_name' => 'Ana', 'last_name' => 'Horvat']);
    $second = Member::factory()->create(['first_name' => 'Ana', 'last_name' => 'Horvat']);
    $other = Member::factory()->create(['first_name' => 'Mia', 'last_name' => 'Kovac']);

    $firstInvoice = Invoice::factory()->create([
        'member_id' => $first->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 80,
        'payment_status' => 'Otvoreno',
        'due_date' => '2026-09-15',
        'reference_code' => '202609-021-001',
    ]);
    $secondInvoice = Invoice::factory()->create([
        'member_id' => $second->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 80,
        'payment_status' => 'Otvoreno',
        'due_date' => '2026-09-15',
        'reference_code' => '202609-022-001',
    ]);
    $otherInvoice = Invoice::factory()->create([
        'member_id' => $other->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 80,
        'payment_status' => 'Otvoreno',
        'due_date' => '2026-09-15',
        'reference_code' => '202609-023-001',
    ]);

    $this->post(route('invoices.importMbanking.store'), [
        'file' => statementCsv([
            ['amount' => '80,00', 'payer' => 'ANA HORVAT', 'reference' => ''],
        ]),
    ])->assertRedirect();

    $import = BankStatementImport::first();
    $line = $import->lines()->first();

    expect($line->kind)->toBe('ambiguous')
        ->and($import->ambiguous_count)->toBe(1);

    $this->post(route('invoices.importMbanking.lines.attach', [$import, $line]), [
        'invoice_id' => $otherInvoice->id,
    ])->assertSessionHasErrors('invoice_id');

    $otherInvoice->refresh();
    expect($otherInvoice->payment_status)->toBe('Otvoreno');

    $this->post(route('invoices.importMbanking.lines.attach', [$import, $line]), [
        'invoice_id' => $firstInvoice->id,
    ])->assertRedirect();

    $firstInvoice->refresh();
    $secondInvoice->refresh();
    $line->refresh();

    expect($firstInvoice->payment_status)->toBe('Plaćeno')
        ->and($secondInvoice->payment_status)->toBe('Otvoreno')
        ->and($line->resolution)->toBe('resolved')
        ->and($line->invoice_id)->toBe($firstInvoice->id);
});

test('a line from another import cannot be changed', function () {
    [$import, $line] = mismatchImport();
    $other = BankStatementImport::create([
        'filename' => 'drugi.csv',
        'imported_at' => now(),
        'total_rows' => 0,
        'incoming_rows' => 0,
        'paid_count' => 0,
        'mismatch_count' => 0,
        'failed_count' => 0,
        'unresolved_count' => 0,
        'ambiguous_count' => 0,
        'skipped_count' => 0,
    ]);

    $this->post(route('invoices.importMbanking.lines.align', [$other, $line]))
        ->assertNotFound();

    $line->refresh();
    expect($line->bank_statement_import_id)->toBe($import->id)
        ->and($line->resolution)->toBe(BankStatementImportLine::RESOLUTION_OPEN);
});

function mismatchImport(): array
{
    $workshop = Workshop::factory()->create();
    $member = Member::factory()->create([
        'first_name' => 'Ivana',
        'last_name' => 'Dujmic',
    ]);
    $oldPlan = statementPlan($workshop, 'Mjesecna 50', 50);
    $newPlan = statementPlan($workshop, 'Mjesecna 225', 225);
    $member->workshops()->attach($workshop->id, [
        'membership_plan_id' => $oldPlan->id,
        'membership_start_date' => '2026-01-01',
    ]);

    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $oldPlan->id,
        'amount_due' => 50,
        'amount_paid' => 0,
        'payment_status' => 'Otvoreno',
        'due_date' => '2026-09-15',
        'reference_code' => '202609-049-001',
    ]);

    test()->post(route('invoices.importMbanking.store'), [
        'file' => statementCsv([
            ['amount' => '225,00', 'reference' => '202609-049-001', 'payer' => 'IVANA DUJMIC'],
        ]),
    ])->assertRedirect();

    $import = BankStatementImport::query()->latest('id')->first();
    $line = $import->lines()->where('kind', 'mismatch')->first();

    return [$import, $line, $invoice, $member, $oldPlan, $newPlan, $workshop];
}

test('a payment that names a member can be linked and noted without changing the invoice', function () {
    $workshop = Workshop::factory()->create();
    $orijana = Member::factory()->create([
        'first_name' => 'Orijana',
        'last_name' => 'Perčić',
    ]);
    Member::factory()->create([
        'first_name' => 'Lea',
        'last_name' => 'Pericic',
    ]);
    $invoice = Invoice::factory()->create([
        'member_id' => $orijana->id,
        'workshop_id' => $workshop->id,
        'amount_due' => 50,
        'payment_status' => 'Otvoreno',
        'due_date' => '2026-01-15',
        'reference_code' => '202601-047-001',
    ]);

    $this->post(route('invoices.importMbanking.store'), [
        'file' => statementCsv([
            [
                'amount' => '50,00',
                'payer' => 'JOSIPA POBOR',
                'reference' => '',
                'description' => 'Orijana Pericic 09-2026',
                'date' => '23.9.2026.',
            ],
        ]),
    ])->assertRedirect();

    $import = BankStatementImport::query()->latest('id')->first();
    $line = $import->lines()->first();

    $this->get(route('invoices.importMbanking.show', $import))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('unresolved.0.suggestions', 0)
            ->has('unresolved.0.member_suggestions', 1)
            ->where('unresolved.0.member_suggestions.0.member_id', $orijana->id)
            ->where('unresolved.0.member_suggestions.0.invoices.0.invoice_id', $invoice->id)
        );

    $this->post(route('invoices.importMbanking.lines.linkMember', [$import, $line]), [
        'member_id' => $orijana->id,
    ])->assertRedirect();

    $this->post(route('invoices.importMbanking.lines.note', [$import, $line]), [
        'note' => 'Platila mama, rujan.',
    ])->assertRedirect();

    $line->refresh();
    $invoice->refresh();

    expect($line->member_id)->toBe($orijana->id)
        ->and($line->resolution)->toBe('open')
        ->and($line->note)->toBe('Platila mama, rujan.')
        ->and($invoice->payment_status)->toBe('Otvoreno')
        ->and((float) $invoice->amount_paid)->toBe(0.0);

    $this->get(route('members.show', $orijana))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('paymentNotes', 1)
            ->where('paymentNotes.0.note', 'Platila mama, rujan.')
            ->where('paymentNotes.0.payer', 'JOSIPA POBOR')
            ->where('paymentNotes.0.amount', 50)
        );
});

test('a member profile shows an import note on the linked invoice', function () {
    $member = Member::factory()->create();
    $invoice = Invoice::factory()->create([
        'member_id' => $member->id,
        'reference_code' => '202609-014-001',
    ]);
    $import = BankStatementImport::query()->create([
        'filename' => 'izvod.csv',
        'imported_at' => now(),
    ]);

    BankStatementImportLine::query()->create([
        'bank_statement_import_id' => $import->id,
        'row_number' => 36,
        'kind' => 'mismatch',
        'payer' => 'ELENA TOMECEK',
        'amount_paid' => 225,
        'reference' => '202609-014-001',
        'value_date' => '2026-09-23',
        'note' => 'Mama uplatila pola.',
        'member_id' => $member->id,
        'invoice_id' => $invoice->id,
        'resolution' => 'open',
    ]);

    BankStatementImportLine::query()->create([
        'bank_statement_import_id' => $import->id,
        'row_number' => 37,
        'kind' => 'paid',
        'payer' => 'ELENA TOMECEK',
        'amount_paid' => 50,
        'member_id' => $member->id,
        'invoice_id' => $invoice->id,
        'resolution' => 'resolved',
    ]);

    $this->get(route('members.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('paymentNotes', 0)
            ->where("invoicesByWorkshop.{$invoice->workshop_id}.0.payment_notes.0.note", 'Mama uplatila pola.')
            ->where("invoicesByWorkshop.{$invoice->workshop_id}.0.payment_notes.0.value_date", '23.09.2026.')
            ->where("invoicesByWorkshop.{$invoice->workshop_id}.0.payment_notes.0.amount", 225)
            ->missing("invoicesByWorkshop.{$invoice->workshop_id}.0.payment_notes.1")
        );
});

test('a payment that names a future month can generate that invoice', function () {
    $workshop = Workshop::factory()->create([
        'name' => 'Dramska radionica',
        'type' => 'Grupno',
    ]);
    $member = Member::factory()->create([
        'first_name' => 'Ema Katerina',
        'last_name' => 'Perović',
        'is_active' => true,
    ]);
    $plan = statementPlan($workshop, 'Mjesečna 50', 50);
    $member->workshops()->attach($workshop->id, [
        'membership_plan_id' => $plan->id,
        'membership_start_date' => '2026-01-01',
    ]);
    $september = Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $plan->id,
        'amount_due' => 50,
        'payment_status' => 'Otvoreno',
        'due_date' => '2026-09-15',
        'reference_code' => '202609-026-001',
    ]);

    $this->post(route('invoices.importMbanking.store'), [
        'file' => statementCsv([
            [
                'amount' => '50,00',
                'payer' => 'SMART POINT',
                'reference' => '130775-372-404',
                'description' => 'Ema Katerina Perovic Clanarina za 10/2026',
                'date' => '7.10.2026.',
            ],
        ]),
    ])->assertRedirect();

    $import = BankStatementImport::query()->latest('id')->first();
    $line = $import->lines()->first();

    $this->get(route('invoices.importMbanking.show', $import))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unresolved.0.member_suggestions.0.member_id', $member->id)
            ->where('unresolved.0.member_suggestions.0.missing_invoices.0.target_month', '2026-10')
            ->where('unresolved.0.member_suggestions.0.missing_invoices.0.label', '10/2026')
            ->where('unresolved.0.member_suggestions.0.missing_invoices.0.workshop_id', $workshop->id)
            ->where('unresolved.0.member_suggestions.0.invoices.0.invoice_id', $september->id)
        );

    $this->post(route('invoices.importMbanking.lines.generateInvoice', [$import, $line]), [
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'target_month' => '2026-10',
    ])->assertRedirect();

    $october = Invoice::query()
        ->where('member_id', $member->id)
        ->whereDate('due_date', '2026-10-15')
        ->first();
    $september->refresh();
    $line->refresh();

    expect($october)->not->toBeNull()
        ->and($october->payment_status)->toBe('Otvoreno')
        ->and((float) $october->amount_due)->toBe(50.0)
        ->and($october->notes)->toBe('Članarina za 10/2026')
        ->and($september->payment_status)->toBe('Otvoreno')
        ->and((float) $september->amount_paid)->toBe(0.0)
        ->and($line->resolution)->toBe('open')
        ->and($line->invoice_id)->toBeNull();

    $this->get(route('invoices.importMbanking.show', $import))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('unresolved.0.member_suggestions.0.missing_invoices', 0)
            ->where('unresolved.0.member_suggestions.0.invoices.0.invoice_id', $october->id)
        );

    $this->post(route('invoices.importMbanking.lines.generateInvoice', [$import, $line]), [
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'target_month' => '2026-09',
    ])->assertSessionHasErrors('target_month');
});

test('a chosen month can be generated when the payment text is not a clean date', function () {
    $workshop = Workshop::factory()->create([
        'name' => 'Dramska radionica',
        'type' => 'Grupno',
    ]);
    $member = Member::factory()->create([
        'first_name' => 'Nola',
        'last_name' => 'Žurga',
        'is_active' => true,
    ]);
    $plan = statementPlan($workshop, 'Mjesečna 50', 50);
    $member->workshops()->attach($workshop->id, [
        'membership_plan_id' => $plan->id,
        'membership_start_date' => '2026-01-01',
    ]);
    Invoice::factory()->create([
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'membership_plan_id' => $plan->id,
        'amount_due' => 50,
        'payment_status' => 'Plaćeno',
        'due_date' => '2026-09-15',
        'reference_code' => '202609-039-001',
    ]);

    $this->post(route('invoices.importMbanking.store'), [
        'file' => statementCsv([
            [
                'amount' => '50,00',
                'payer' => 'DIANA ZURGA',
                'reference' => '',
                'description' => 'Nola Zurga,10/ 2026.',
                'date' => '9.10.2026.',
            ],
        ]),
    ])->assertRedirect();

    $spaced = BankStatementImport::query()->latest('id')->first();

    $this->get(route('invoices.importMbanking.show', $spaced))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unresolved.0.member_suggestions.0.missing_invoices.0.target_month', '2026-10')
            ->where('unresolved.0.member_suggestions.0.workshops.0.workshop_id', $workshop->id)
        );

    $this->post(route('invoices.importMbanking.store'), [
        'file' => statementCsv([
            [
                'amount' => '50,00',
                'payer' => 'SMART POINT',
                'reference' => '',
                'description' => 'Nola Zurga uplata',
                'date' => '9.10.2026.',
            ],
        ]),
    ])->assertRedirect();

    $import = BankStatementImport::query()->latest('id')->first();
    $line = $import->lines()->first();

    $this->get(route('invoices.importMbanking.show', $import))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unresolved.0.member_suggestions.0.member_id', $member->id)
            ->has('unresolved.0.member_suggestions.0.missing_invoices', 0)
            ->where('unresolved.0.member_suggestions.0.suggested_month', '2026-10')
            ->where('unresolved.0.member_suggestions.0.workshops.0.workshop_id', $workshop->id)
        );

    $this->post(route('invoices.importMbanking.lines.generateInvoice', [$import, $line]), [
        'member_id' => $member->id,
        'workshop_id' => $workshop->id,
        'target_month' => '2026-10',
    ])->assertRedirect();

    $october = Invoice::query()
        ->where('member_id', $member->id)
        ->whereDate('due_date', '2026-10-15')
        ->first();

    expect($october)->not->toBeNull()
        ->and($october->payment_status)->toBe('Otvoreno')
        ->and((float) $october->amount_due)->toBe(50.0);
});
