<?php

namespace App\Support;

/**
 * Comma-separated inbox allowlist from `MAIL_RECIPIENT_ALLOWLIST`.
 * An empty list is unrestricted. A non-empty list is the only set of
 * addresses a slip send or `mail:test` may use.
 */
final class MailRecipientAllowlist
{
    public const REFUSAL_MESSAGE = 'Slanje je odbijeno jer primatelj nije na popisu dopuštenih adresa (MAIL_RECIPIENT_ALLOWLIST). Nijedna uplatnica nije stavljena u red.';

    public const BLOCKED_MESSAGE = 'Primatelj nije na popisu dopuštenih adresa (MAIL_RECIPIENT_ALLOWLIST), pa e-pošta nije poslana.';

    /**
     * @return list<string> lowercased addresses; empty means unrestricted
     */
    public static function addresses(): array
    {
        $configured = config('mail.recipient_allowlist', '');

        if (is_array($configured)) {
            $parts = $configured;
        } else {
            $parts = explode(',', (string) $configured);
        }

        $addresses = [];

        foreach ($parts as $part) {
            $email = mb_strtolower(trim((string) $part));

            if ($email !== '') {
                $addresses[$email] = $email;
            }
        }

        return array_values($addresses);
    }

    public static function restricts(): bool
    {
        return self::addresses() !== [];
    }

    public static function allows(string $email): bool
    {
        $addresses = self::addresses();

        if ($addresses === []) {
            return true;
        }

        return in_array(mb_strtolower(trim($email)), $addresses, true);
    }
}
