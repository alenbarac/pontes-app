<?php

use App\Support\MailRecipientAllowlist;

afterEach(function () {
    config(['mail.recipient_allowlist' => '']);
});

test('an empty allowlist allows every recipient', function () {
    config(['mail.recipient_allowlist' => '']);

    expect(MailRecipientAllowlist::restricts())->toBeFalse()
        ->and(MailRecipientAllowlist::allows('parent@example.test'))->toBeTrue();

    config(['mail.recipient_allowlist' => null]);

    expect(MailRecipientAllowlist::allows('parent@example.test'))->toBeTrue();
});

test('the allowlist trims entries and ignores case', function () {
    config(['mail.recipient_allowlist' => ' Staff@Example.test, other@example.test ,']);

    expect(MailRecipientAllowlist::addresses())->toBe([
        'staff@example.test',
        'other@example.test',
    ])
        ->and(MailRecipientAllowlist::restricts())->toBeTrue()
        ->and(MailRecipientAllowlist::allows('staff@example.test'))->toBeTrue()
        ->and(MailRecipientAllowlist::allows('OTHER@example.test'))->toBeTrue()
        ->and(MailRecipientAllowlist::allows('parent@example.test'))->toBeFalse();
});
