<?php

use App\Jobs\SendInvoiceMailing;
use Illuminate\Foundation\Application;

test('the app can boot a laravel cloud managed queue', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($composer['require']['laravel/framework'])->toBe('^11.55')
        ->and($composer['require'])->toHaveKey('aws/aws-sdk-php')
        ->and(version_compare(Application::VERSION, '11.55.0', '>='))->toBeTrue()
        ->and(class_exists(Illuminate\Foundation\Cloud::class))->toBeTrue()
        ->and(class_exists(Aws\Sqs\SqsClient::class))->toBeTrue();
});

test('local queue config stays on the database driver', function () {
    $queue = file_get_contents(base_path('config/queue.php'));
    $example = file_get_contents(base_path('.env.example'));

    expect($queue)->toContain("env('QUEUE_CONNECTION', 'database')")
        ->and($example)->toMatch('/^QUEUE_CONNECTION=database$/m')
        ->and($example)->not->toMatch('/^QUEUE_CONNECTION=(sync|cloud)$/m')
        ->and($example)->toContain('QUEUE_CONNECTION=cloud');
});

test('staging mail docs keep the sandbox host at one send per second', function () {
    $example = file_get_contents(base_path('.env.example'));
    $readme = file_get_contents(base_path('README.md'));
    $phpunit = file_get_contents(base_path('phpunit.xml'));

    expect($example)->toContain('sandbox.smtp.mailtrap.io')
        ->and($example)->toContain('live.smtp.mailtrap.io')
        ->and($example)->toMatch('/^MAIL_BULK_SENDS_PER_SECOND=1$/m')
        ->and($readme)->toContain('sandbox.smtp.mailtrap.io')
        ->and($readme)->toContain('live.smtp.mailtrap.io')
        ->and($readme)->toContain('QUEUE_CONNECTION=cloud')
        ->and($readme)->toContain('Evidencija slanja')
        ->and($readme)->toContain('MAIL_BULK_SENDS_PER_SECOND=1')
        ->and($phpunit)->toContain('<env name="MAIL_MAILER" value="array"/>');

    config([
        'mail.bulk_sends_per_second' => '1',
        'mail.bulk_throttle_seconds' => 0,
    ]);

    $job = new SendInvoiceMailing(1);

    expect(SendInvoiceMailing::sendsPerSecond())->toBe(1)
        ->and(SendInvoiceMailing::secondsUntilNextSend())->toBe(2)
        ->and($job->timeout)->toBeLessThan(90)
        ->and($job->maxExceptions)->toBe(3);
});
