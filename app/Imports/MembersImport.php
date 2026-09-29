<?php

namespace App\Imports;

use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberGroupWorkshop;
use App\Models\MembershipPlan;
use App\Models\Workshop;
use App\Services\SchoolYearService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class MembersImport implements ToCollection, WithColumnLimit, WithHeadingRow, WithReadFilter
{
    private $errors = [];

    private $createdCount = 0;

    private $updatedCount = 0;

    private $failedCount = 0;

    private $discounted = [];

    private ?Collection $memberGroups = null;

    public function endColumn(): string
    {
        return 'F';
    }

    public function readFilter(): IReadFilter
    {
        return new class implements IReadFilter
        {
            public function readCell($columnAddress, $row, $worksheetName = '')
            {
                return strlen((string) $columnAddress) === 1
                    && strtoupper((string) $columnAddress) <= 'F';
            }
        };
    }

    public function collection(Collection $rows)
    {
        if ($rows->isNotEmpty()) {
            $firstRowKeys = $rows->first()->keys()->take(12)->values()->all();
            Log::info('Available header keys in Excel:', $firstRowKeys);
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because index is 0-based and we skip header row

            // Laravel Excel normalizes headers (lowercase, spaces to underscores, etc.)
            // Try multiple variations to find the correct keys
            $grupa = $this->getValue($row, [
                'grupa', 'GRUPA', 'Grupa',
                'grupa_', '_grupa',
            ]);
            $imePrezime = $this->getValue($row, [
                'ime i prezime', 'IME I PREZIME', 'Ime i prezime',
                'ime i prezime:', 'IME I PREZIME:', 'Ime i prezime:',
                'ime_i_prezime', 'ime_i_prezime_', '_ime_i_prezime',
                'ime i prezime_', '_ime i prezime',
            ]);
            $email = $this->getValue($row, [
                'e-mail', 'E-MAIL', 'E-mail', 'email',
                'e_mail', 'e-mail_', '_e-mail',
                'email_', '_email',
                'e-mail adresa', 'e_mail_adresa', 'email adresa',
            ]);
            $imeUplatnica = $this->getValue($row, [
                'ime uplatnica', 'IME UPLATNICA', 'Ime uplatnica',
                'ime_uplatnica', 'ime_uplatnica_', '_ime_uplatnica',
            ]);
            $clanarina = $this->getValue($row, [
                'članarina', 'ČLANARINA', 'Članarina',
                'članarina_', '_članarina', 'clanarina',
            ]);

            // Skip empty rows
            if (empty($grupa) && empty($imePrezime) && empty($email) && empty($clanarina)) {
                continue;
            }

            $rowErrors = [];

            // Validate required fields
            if (empty($imePrezime)) {
                $rowErrors[] = 'Ime i prezime je obavezno.';
            }

            // Email is optional (some rows may not have it). Siblings often share a parent email.
            if (! empty($email) && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = 'E-mail nije valjan.';
            }

            if (empty($clanarina)) {
                $rowErrors[] = 'Članarina je obavezna.';
            }

            if (empty($grupa)) {
                $rowErrors[] = 'Grupa je obavezna.';
            }

            // If there are validation errors, skip this row
            if (! empty($rowErrors)) {
                $this->failedCount++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => implode(' ', $rowErrors),
                    'data' => [
                        'grupa' => $grupa,
                        'ime_prezime' => $imePrezime,
                        'email' => $email,
                        'clanarina' => $clanarina,
                    ],
                ];

                continue;
            }

            // Split full name (trailing * in the spreadsheet marks a note, not part of the name)
            [$firstName, $lastName] = $this->splitFullName($imePrezime);
            $slipPayerName = $this->resolveSlipPayerName($imeUplatnica);

            // Find member group by name (case-insensitive, space-normalized)
            // This allows matching "Memorabilije 1", "Memorabilije1", "memorabilije 1", etc.
            $memberGroup = $this->findMemberGroup($grupa);

            if (! $memberGroup) {
                $this->failedCount++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => "Grupa '{$grupa}' nije pronađena.",
                    'data' => [
                        'grupa' => $grupa,
                        'ime_prezime' => $imePrezime,
                        'email' => $email,
                        'clanarina' => $clanarina,
                    ],
                ];

                continue;
            }

            // Find workshop(s) associated with this member group via workshop_groups table
            $workshopGroup = DB::table('workshop_groups')
                ->where('member_group_id', $memberGroup->id)
                ->first();

            if (! $workshopGroup) {
                $this->failedCount++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => "Grupa '{$grupa}' nije povezana s radionicom.",
                    'data' => [
                        'grupa' => $grupa,
                        'ime_prezime' => $imePrezime,
                        'email' => $email,
                        'clanarina' => $clanarina,
                    ],
                ];

                continue;
            }

            $workshop = Workshop::find($workshopGroup->workshop_id);

            if (! $workshop) {
                $this->failedCount++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => "Radionica za grupu '{$grupa}' nije pronađena.",
                    'data' => [
                        'grupa' => $grupa,
                        'ime_prezime' => $imePrezime,
                        'email' => $email,
                        'clanarina' => $clanarina,
                    ],
                ];

                continue;
            }

            $parsedClanarina = $this->parseClanarina($clanarina);
            $membershipPlan = $this->findMembershipPlan($workshop->id, $parsedClanarina['label']);

            if (! $membershipPlan) {
                $this->failedCount++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => "Članarina '{$clanarina}' nije pronađena za grupu '{$grupa}'.",
                    'data' => [
                        'grupa' => $grupa,
                        'ime_prezime' => $imePrezime,
                        'email' => $email,
                        'clanarina' => $clanarina,
                    ],
                ];

                continue;
            }

            if ($parsedClanarina['discount_percent']) {
                $membershipPlan = $this->planWithDiscount(
                    $membershipPlan,
                    $parsedClanarina['discount_percent']
                );
            }

            try {
                $existingMember = $this->findExistingMember($firstName, $lastName);

                if ($existingMember) {
                    $this->applySlipPayerName($existingMember, $slipPayerName);
                    $this->syncContactEmail($existingMember, $email);
                    $this->syncEnrollment($existingMember, $workshop, $membershipPlan, $memberGroup);
                    $this->updatedCount++;
                    $this->recordDiscounted(
                        $parsedClanarina['discount_percent'],
                        $rowNumber,
                        $imePrezime,
                        $membershipPlan
                    );

                    continue;
                }

                $memberEmail = $email;
                if (empty($memberEmail) || Member::where('email', $memberEmail)->exists()) {
                    $memberEmail = $this->generateUniqueEmail($firstName, $lastName);
                }

                $member = Member::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'date_of_birth' => '2000-01-01', // Default date, can be updated later
                    'phone_number' => 'N/A', // Placeholder, can be updated later
                    'email' => $memberEmail,
                    'invoice_email' => $email ?: null,
                    'parent_email' => $email ?: null,
                    'slip_payer_name' => $slipPayerName,
                    'is_active' => true,
                ]);

                $this->syncEnrollment($member, $workshop, $membershipPlan, $memberGroup);
                $this->createdCount++;
                $this->recordDiscounted(
                    $parsedClanarina['discount_percent'],
                    $rowNumber,
                    $imePrezime,
                    $membershipPlan
                );
            } catch (\Exception $e) {
                $this->failedCount++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Greška pri kreiranju člana: '.$e->getMessage(),
                    'data' => [
                        'grupa' => $grupa,
                        'ime_prezime' => $imePrezime,
                        'email' => $email,
                        'clanarina' => $clanarina,
                    ],
                ];
            }
        }
    }

    /**
     * Split a ČLANARINA cell into the base plan label and an optional % OFF.
     * Example: "Godišnja članarina * 20 OFF" → yearly plan with 20% discount.
     *
     * @return array{label: string, discount_percent: int|null}
     */
    private function parseClanarina(string $clanarina): array
    {
        $clanarina = trim($clanarina);

        if (preg_match('/^(.*?)\s*[\*x×]\s*(\d{1,2})\s*%?\s*off\s*$/iu', $clanarina, $matches)) {
            $percent = (int) $matches[2];
            if ($percent > 0 && $percent < 100) {
                return [
                    'label' => trim($matches[1]),
                    'discount_percent' => $percent,
                ];
            }
        }

        return [
            'label' => $clanarina,
            'discount_percent' => null,
        ];
    }

    /**
     * Resolve a spreadsheet ČLANARINA value to a workshop membership plan.
     *
     * Naive substring matching is unsafe: "Polugodišnja članarina" contains
     * "Godišnja članarina" and would otherwise be assigned the yearly plan.
     */
    private function findMembershipPlan(int $workshopId, string $clanarina): ?MembershipPlan
    {
        $plans = MembershipPlan::where('workshop_id', $workshopId)
            ->get()
            ->filter(fn (MembershipPlan $plan) => ! $plan->isDiscounted());
        if ($plans->isEmpty()) {
            return null;
        }

        $normalizedInput = $this->normalizePlanLabel($clanarina);

        $exactName = $plans->first(
            fn (MembershipPlan $plan) => $this->normalizePlanLabel($plan->plan) === $normalizedInput
        );
        if ($exactName) {
            return $exactName;
        }

        $exactFrequency = $plans->first(
            fn (MembershipPlan $plan) => $this->normalizePlanLabel((string) $plan->billing_frequency) === $normalizedInput
        );
        if ($exactFrequency) {
            return $exactFrequency;
        }

        $inputFrequency = $this->inferBillingFrequency($normalizedInput);
        if ($inputFrequency) {
            return $plans->first(function (MembershipPlan $plan) use ($inputFrequency) {
                return $this->inferBillingFrequency($this->normalizePlanLabel($plan->plan)) === $inputFrequency
                    || $this->inferBillingFrequency($this->normalizePlanLabel((string) $plan->billing_frequency)) === $inputFrequency;
            });
        }

        return $plans->first(function (MembershipPlan $plan) use ($normalizedInput) {
            $normalizedPlanName = $this->normalizePlanLabel($plan->plan);

            return str_contains(" {$normalizedPlanName} ", " {$normalizedInput} ")
                || str_contains(" {$normalizedInput} ", " {$normalizedPlanName} ");
        });
    }

    /**
     * Find or create a workshop plan variant with a percentage discount applied
     * to the catalog total_fee (the amount used for invoices).
     */
    private function planWithDiscount(MembershipPlan $basePlan, int $percent): MembershipPlan
    {
        $discountedName = sprintf('%s (%d%% OFF)', $basePlan->plan, $percent);
        $baseAmount = (float) ($basePlan->total_fee ?? $basePlan->fee);
        $totalFee = round($baseAmount * (1 - $percent / 100), 2);

        return MembershipPlan::firstOrCreate(
            [
                'workshop_id' => $basePlan->workshop_id,
                'plan' => $discountedName,
            ],
            [
                'fee' => $basePlan->fee,
                'billing_frequency' => $basePlan->billing_frequency,
                'discount_type' => $percent.'% OFF',
                'total_fee' => $totalFee,
            ]
        );
    }

    /**
     * Lowercase, fold Croatian diacritics, collapse punctuation to spaces.
     */
    private function normalizePlanLabel(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'č' => 'c',
            'ć' => 'c',
            'š' => 's',
            'ž' => 'z',
            'đ' => 'd',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /**
     * Map a normalized plan label to a billing-frequency bucket.
     * Semi-annual is checked before yearly so "polugodišnja" is not treated as "godišnja".
     */
    private function inferBillingFrequency(string $normalized): ?string
    {
        if (
            str_contains($normalized, 'polugodisnj')
            || (str_contains($normalized, 'polu') && str_contains($normalized, 'godisnj'))
            || preg_match('/\b6\s*mjesec/', $normalized)
            || str_contains($normalized, 'semi annual')
            || str_contains($normalized, 'semiannual')
        ) {
            return 'semi-annual';
        }

        if (str_contains($normalized, 'sastanku') || str_contains($normalized, 'per session')) {
            return 'per-session';
        }

        if (str_contains($normalized, 'mjesecn') || str_contains($normalized, 'monthly')) {
            return 'monthly';
        }

        if (
            str_contains($normalized, 'godisnj')
            || str_contains($normalized, 'yearly')
            || str_contains($normalized, 'annual')
        ) {
            return 'yearly';
        }

        return null;
    }

    private function findMemberGroup(string $grupa): ?MemberGroup
    {
        $normalizedGrupa = str_replace(' ', '', strtolower(trim($grupa)));
        $this->memberGroups ??= MemberGroup::all();

        return $this->memberGroups->first(function ($group) use ($normalizedGrupa) {
            $normalizedGroupName = str_replace(' ', '', strtolower(trim($group->name)));

            return $normalizedGroupName === $normalizedGrupa;
        });
    }

    /**
     * Get value from row by trying multiple header variations
     */
    private function getValue(Collection $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            // Try exact key
            if (isset($row[$key])) {
                $value = $row[$key];

                return is_null($value) ? null : trim((string) $value);
            }

            foreach ($row->keys() as $rowKey) {
                if (! is_string($rowKey) || $rowKey === '') {
                    continue;
                }

                $normalizedRowKey = $this->normalizeKey($rowKey);
                $normalizedKey = $this->normalizeKey($key);

                if ($normalizedRowKey === $normalizedKey) {
                    $value = $row[$rowKey];

                    return is_null($value) ? null : trim((string) $value);
                }
            }
        }

        return null;
    }

    /**
     * Normalize header key for comparison (lowercase, remove special chars, normalize spaces)
     */
    private function normalizeKey(string $key): string
    {
        // Convert to lowercase
        $key = mb_strtolower($key, 'UTF-8');

        // Remove colons and other punctuation
        $key = str_replace([':', ';', ',', '.'], '', $key);

        // Replace spaces and hyphens with underscores
        $key = str_replace([' ', '-', '_'], '_', $key);

        // Remove multiple underscores
        $key = preg_replace('/_+/', '_', $key);

        // Trim underscores from start and end
        $key = trim($key, '_');

        return $key;
    }

    /**
     * Generate a unique email for members without email
     */
    private function generateUniqueEmail(string $firstName, string $lastName): string
    {
        $base = strtolower(Str::ascii($firstName.'.'.$lastName));
        $base = preg_replace('/[^a-z0-9]/', '', $base);
        $base = substr($base, 0, 20); // Limit length

        $email = $base.'@import.local';
        $counter = 1;

        // Ensure uniqueness
        while (Member::where('email', $email)->exists()) {
            $email = $base.$counter.'@import.local';
            $counter++;
        }

        return $email;
    }

    /**
     * IME UPLATNICA is the platitelj override on the payment slip.
     * Empty cells stay null so a re-import does not wipe an existing name.
     */
    private function resolveSlipPayerName(?string $imeUplatnica): ?string
    {
        $value = trim((string) $imeUplatnica);

        return $value === '' ? null : $value;
    }

    /**
     * The spreadsheet address replaces email, invoice_email, and parent_email
     * when it differs. An empty cell leaves the stored addresses in place.
     * members.email is unique, so a shared address is kept on the invoice
     * fields when another member already uses it as their login email.
     */
    private function syncContactEmail(Member $member, ?string $email): void
    {
        $email = trim((string) $email);
        if ($email === '') {
            return;
        }

        $updates = [];

        if ($member->invoice_email !== $email) {
            $updates['invoice_email'] = $email;
        }

        if ($member->parent_email !== $email) {
            $updates['parent_email'] = $email;
        }

        $takenByAnother = Member::query()
            ->where('email', $email)
            ->where('id', '!=', $member->id)
            ->exists();

        if (! $takenByAnother && $member->email !== $email) {
            $updates['email'] = $email;
        }

        if ($updates !== []) {
            $member->update($updates);
        }
    }

    private function applySlipPayerName(Member $member, ?string $slipPayerName): void
    {
        if ($slipPayerName === null) {
            return;
        }

        if ($member->slip_payer_name === $slipPayerName) {
            return;
        }

        $member->update(['slip_payer_name' => $slipPayerName]);
    }

    /**
     * Split full name by last space into first_name and last_name
     */
    private function splitFullName(string $fullName): array
    {
        $fullName = trim(preg_replace('/\*+$/', '', trim($fullName)));
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));
        $pos = strrpos($fullName, ' ');

        if ($pos === false) {
            return [$fullName, ''];
        }

        return [
            substr($fullName, 0, $pos),
            substr($fullName, $pos + 1),
        ];
    }

    private function findExistingMember(string $firstName, string $lastName): ?Member
    {
        return Member::query()
            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower(trim($firstName))])
            ->whereRaw('LOWER(COALESCE(last_name, "")) = ?', [mb_strtolower(trim($lastName))])
            ->first();
    }

    private function syncEnrollment(
        Member $member,
        Workshop $workshop,
        MembershipPlan $plan,
        MemberGroup $group
    ): void {
        if ($member->workshops()->where('workshops.id', $workshop->id)->exists()) {
            $member->workshops()->updateExistingPivot($workshop->id, [
                'membership_plan_id' => $plan->id,
            ]);
        } else {
            $member->workshops()->attach($workshop->id, [
                'membership_plan_id' => $plan->id,
                'membership_start_date' => SchoolYearService::getCurrentSchoolYear()['start'],
            ]);
        }

        $assignment = MemberGroupWorkshop::where('member_id', $member->id)
            ->where('workshop_id', $workshop->id)
            ->first();

        if ($assignment) {
            $assignment->update(['member_group_id' => $group->id]);
        } else {
            MemberGroupWorkshop::create([
                'member_id' => $member->id,
                'workshop_id' => $workshop->id,
                'member_group_id' => $group->id,
            ]);
        }
    }

    private function recordDiscounted(
        ?int $discountPercent,
        int $rowNumber,
        string $imePrezime,
        MembershipPlan $plan
    ): void {
        if (! $discountPercent) {
            return;
        }

        $this->discounted[] = [
            'row' => $rowNumber,
            'ime_prezime' => $imePrezime,
            'plan' => $plan->plan,
            'discount_percent' => $discountPercent,
            'total_fee' => $plan->total_fee,
        ];
    }

    /**
     * Get import results
     */
    public function getResults(): array
    {
        return [
            'created_count' => $this->createdCount,
            'updated_count' => $this->updatedCount,
            'failed_count' => $this->failedCount,
            'discounted_count' => count($this->discounted),
            'discounted' => $this->discounted,
            'errors' => $this->errors,
        ];
    }
}
