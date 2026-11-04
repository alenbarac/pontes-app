<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EmailTemplate extends Model
{
    public const SLIP = 'payment_slip';

    public const REMINDER = 'payment_reminder';

    public const DOCUMENT = 'member_document';

    protected $fillable = [
        'key',
        'subject',
        'heading',
        'greeting',
        'intro',
        'sign_off',
    ];

    /**
     * Catalog of templates the app knows how to send.
     * Message text lives in the database; name and description stay here.
     *
     * @return array<string, array{name: string, description: string, subject: string, heading: string, greeting: string, intro: string, sign_off: string}>
     */
    public static function defaults(): array
    {
        return [
            self::SLIP => [
                'name' => 'Uplatnica',
                'description' => 'E-mail s uplatnicom u prilogu.',
                'subject' => 'Uplatnica - {opis}',
                'heading' => 'Uplatnica - {studio}',
                'greeting' => 'Poštovani/na {ime},',
                'intro' => 'U prilogu Vam šaljemo uplatnicu',
                'sign_off' => 'Lijep pozdrav,',
            ],
            self::REMINDER => [
                'name' => 'Podsjetnik za uplatu',
                'description' => 'Podsjetnik za nepodmireni račun, s uplatnicom u prilogu.',
                'subject' => 'Podsjetnik za uplatu - {opis}',
                'heading' => 'Podsjetnik za uplatu',
                'greeting' => 'Poštovani/na {ime},',
                'intro' => 'Unutar naše evidencije ustanovili smo da nije podmiren račun. U prilogu ponovno šaljemo uplatnicu.',
                'sign_off' => 'Lijep pozdrav,',
            ],
            self::DOCUMENT => [
                'name' => 'Dokument člana',
                'description' => 'E-mail s dokumentom u prilogu (ispričnica, privola i kasnije vrste).',
                'subject' => '{naziv_dokumenta} - {ime}',
                'heading' => '{naziv_dokumenta}',
                'greeting' => 'Poštovani/na {ime},',
                'intro' => 'U prilogu Vam šaljemo dokument {naziv_dokumenta}.',
                'sign_off' => 'Lijep pozdrav,',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function placeholders(?string $key = null): array
    {
        if ($key === self::DOCUMENT) {
            return [
                '{ime}' => 'Ime člana',
                '{naziv_dokumenta}' => 'Naziv dokumenta, npr. Ispričnica',
                '{studio}' => 'Naziv studija',
            ];
        }

        return [
            '{ime}' => 'Ime platitelja ili člana',
            '{opis}' => 'Opis članarine, npr. Članarina za 10/2026',
            '{studio}' => 'Naziv studija',
        ];
    }

    public static function forKey(string $key): self
    {
        $defaults = self::defaults()[$key] ?? null;

        if ($defaults === null) {
            throw new InvalidArgumentException("Unknown email template [{$key}].");
        }

        return self::query()->firstOrCreate(
            ['key' => $key],
            [
                'subject' => $defaults['subject'],
                'heading' => $defaults['heading'],
                'greeting' => $defaults['greeting'],
                'intro' => $defaults['intro'],
                'sign_off' => $defaults['sign_off'],
            ],
        );
    }

    /**
     * @param  array{ime?: string, opis?: string, studio?: string, naziv_dokumenta?: string}  $values
     */
    public static function interpolate(string $template, array $values = []): string
    {
        $ime = trim((string) ($values['ime'] ?? ''));
        $studio = trim((string) ($values['studio'] ?? ''));
        $opis = trim((string) ($values['opis'] ?? ''));
        $nazivDokumenta = trim((string) ($values['naziv_dokumenta'] ?? ''));

        if ($ime === '') {
            $template = preg_replace('/\s*\/na\s*\{ime\}/u', '', $template) ?? $template;
        }

        if ($studio === '') {
            $template = str_replace(' - {studio}', '', $template);
        }

        if ($nazivDokumenta === '') {
            $template = str_replace('{naziv_dokumenta} - ', '', $template);
            $template = str_replace(' - {naziv_dokumenta}', '', $template);
        }

        $rendered = strtr($template, [
            '{ime}' => $ime,
            '{studio}' => $studio,
            '{opis}' => $opis,
            '{naziv_dokumenta}' => $nazivDokumenta,
        ]);

        $rendered = preg_replace('/[ \t]{2,}/u', ' ', $rendered) ?? $rendered;
        $rendered = preg_replace('/[ \t]+([,!.])/u', '$1', $rendered) ?? $rendered;

        return trim($rendered);
    }

    /**
     * @return array<string, string>
     */
    public function messageDefaults(): array
    {
        $defaults = self::defaults()[$this->key] ?? [];

        return [
            'subject' => $defaults['subject'] ?? '',
            'heading' => $defaults['heading'] ?? '',
            'greeting' => $defaults['greeting'] ?? '',
            'intro' => $defaults['intro'] ?? '',
            'sign_off' => $defaults['sign_off'] ?? '',
        ];
    }
}
