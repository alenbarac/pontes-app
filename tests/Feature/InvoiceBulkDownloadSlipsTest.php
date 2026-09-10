<?php

use App\Http\Requests\BulkDownloadInvoiceSlipsRequest;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Models\Workshop;
use App\Services\PaymentSlipPdfService;
use Barryvdh\DomPDF\PDF as PdfDocument;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $pdf = Mockery::mock(PdfDocument::class);
    $pdf->shouldReceive('output')->andReturn('%PDF-fake');

    $this->partialMock(PaymentSlipPdfService::class, function ($mock) use ($pdf) {
        $mock->shouldReceive('generate')->andReturn($pdf);
    });
});

test('bulk download returns a zip of selected invoice slips', function () {
    $workshop = Workshop::factory()->create(['name' => 'Dramska radionica']);
    $plan = MembershipPlan::create([
        'workshop_id' => $workshop->id,
        'plan' => 'Godišnja članarina',
        'fee' => 450,
        'billing_frequency' => 'godišnje',
        'total_fee' => 450,
    ]);

    $mia = Member::factory()->create(['first_name' => 'Mia', 'last_name' => 'Grgurić']);
    $leo = Member::factory()->create(['first_name' => 'Leo', 'last_name' => 'Horvat']);

    $invoices = collect([
        Invoice::factory()->create([
            'member_id' => $mia->id,
            'workshop_id' => $workshop->id,
            'membership_plan_id' => $plan->id,
            'due_date' => '2026-09-15',
            'reference_code' => '202609-001-001',
        ]),
        Invoice::factory()->create([
            'member_id' => $leo->id,
            'workshop_id' => $workshop->id,
            'membership_plan_id' => $plan->id,
            'due_date' => '2026-09-15',
            'reference_code' => '202609-002-001',
        ]),
    ]);

    $response = $this->post(route('invoices.bulkDownloadSlips'), [
        'invoice_ids' => $invoices->pluck('id')->all(),
    ]);

    $response->assertOk()
        ->assertDownload('uplatnice-godisnja-clanarina-2026-09.zip');

    $zipPath = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    expect($zip->open($zipPath))->toBeTrue()
        ->and($zip->numFiles)->toBe(2);

    $names = collect(range(0, $zip->numFiles - 1))
        ->map(fn ($i) => $zip->getNameIndex($i))
        ->all();

    expect($names)->toContain('Mia-Grguric-202609-001-001.pdf')
        ->and($names)->toContain('Leo-Horvat-202609-002-001.pdf');

    $zip->close();
});

test('bulk download rejects batches over the cap', function () {
    $invoices = Invoice::factory()->count(2)->create();
    $oversized = array_pad($invoices->pluck('id')->all(), BulkDownloadInvoiceSlipsRequest::MAX_BATCH + 1, $invoices->first()->id);

    $this->postJson(route('invoices.bulkDownloadSlips'), [
        'invoice_ids' => $oversized,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['invoice_ids']);
});

test('zip download name falls back when invoices span multiple plans', function () {
    $service = app(PaymentSlipPdfService::class);

    $workshop = Workshop::factory()->create();
    $yearly = MembershipPlan::create([
        'workshop_id' => $workshop->id,
        'plan' => 'Godišnja članarina',
        'fee' => 450,
        'billing_frequency' => 'godišnje',
        'total_fee' => 450,
    ]);
    $monthly = MembershipPlan::create([
        'workshop_id' => $workshop->id,
        'plan' => 'Mjesečna članarina',
        'fee' => 50,
        'billing_frequency' => 'mjesečno',
        'total_fee' => 50,
    ]);

    $invoices = Invoice::query()->whereIn('id', [
        Invoice::factory()->create([
            'workshop_id' => $workshop->id,
            'membership_plan_id' => $yearly->id,
            'due_date' => '2026-09-15',
        ])->id,
        Invoice::factory()->create([
            'workshop_id' => $workshop->id,
            'membership_plan_id' => $monthly->id,
            'due_date' => '2026-09-15',
        ])->id,
    ])->get();

    expect($service->zipDownloadName($invoices))->toBe('uplatnice-racuni-2026-09.zip');
});
