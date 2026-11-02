<?php

namespace App\Models;

use App\Support\MailRecipientAllowlist;
use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $fillable = [
        'test_sending',
    ];

    protected $casts = [
        'test_sending' => 'boolean',
    ];

    public static function current(): self
    {
        $setting = static::query()->find(1);

        if ($setting instanceof self) {
            return $setting;
        }

        return static::query()->create([
            'id' => 1,
            'test_sending' => true,
        ]);
    }

    public static function testSending(): bool
    {
        return static::current()->test_sending;
    }

    /**
     * Test mode uses the environment allowlist. Live mode delivers to the member address.
     * Test mode with an empty list permits nobody.
     */
    public static function permits(string $email): bool
    {
        if (! static::testSending()) {
            return true;
        }

        if (! MailRecipientAllowlist::restricts()) {
            return false;
        }

        return MailRecipientAllowlist::allows($email);
    }

    /**
     * @param  iterable<mixed>  $emails
     */
    public static function blockMessageFor(iterable $emails): ?string
    {
        if (! static::testSending()) {
            return null;
        }

        if (! MailRecipientAllowlist::restricts()) {
            return MailRecipientAllowlist::EMPTY_LIST_MESSAGE;
        }

        foreach ($emails as $email) {
            if (! MailRecipientAllowlist::allows((string) $email)) {
                return MailRecipientAllowlist::REFUSAL_MESSAGE;
            }
        }

        return null;
    }
}
