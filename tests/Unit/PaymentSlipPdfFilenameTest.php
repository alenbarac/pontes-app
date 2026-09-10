<?php

use App\Models\Invoice;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\PaymentSlipPdfService;
use Illuminate\Support\Collection;

test('pdf filename transliterates croatian characters', function () {
    $invoice = new Invoice(['reference_code' => '202609-001-001']);
    $invoice->setRelation('member', new Member([
        'first_name' => 'Mia',
        'last_name' => 'Grgurić',
    ]));

    expect(app(PaymentSlipPdfService::class)->pdfFilename($invoice))
        ->toBe('Mia-Grguric-202609-001-001.pdf');
});

test('zip name uses the shared plan and month', function () {
    $plan = new MembershipPlan(['plan' => 'Godišnja članarina']);
    $invoice = new Invoice(['due_date' => '2026-09-15']);
    $invoice->setRelation('membershipPlan', $plan);

    expect(app(PaymentSlipPdfService::class)->zipDownloadName(new Collection([$invoice])))
        ->toBe('uplatnice-godisnja-clanarina-2026-09.zip');
});
