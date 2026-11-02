<?php

namespace Tests;

use App\Models\MailSetting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('mail_settings')) {
            MailSetting::query()->whereKey(1)->update(['test_sending' => false]);
        }
    }
}
