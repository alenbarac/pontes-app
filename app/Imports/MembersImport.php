<?php

namespace App\Imports;

use App\Models\Member;
use App\Models\Workshop;
use App\Models\MemberGroup;
use App\Models\MembershipPlan;
use App\Models\MemberWorkshop;
use App\Models\MemberGroupWorkshop;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MembersImport implements ToCollection, WithHeadingRow
{
    private $errors = [];
    private $createdCount = 0;
    private $failedCount = 0;
    private $seenEmails = [];

    public function collection(Collection $rows)
    {
        // Debug: Log available keys from first row (only once)
        if ($rows->isNotEmpty()) {
            $firstRowKeys = $rows->first()->keys()->toArray();
            \Log::info('Available header keys in Excel:', $firstRowKeys);
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because index is 0-based and we skip header row

            // Laravel Excel normalizes headers (lowercase, spaces to underscores, etc.)
            // Try multiple variations to find the correct keys
            $grupa = $this->getValue($row, [
                'grupa', 'GRUPA', 'Grupa',
                'grupa_', '_grupa'
            ]);
            $imePrezime = $this->getValue($row, [
                'ime i prezime', 'IME I PREZIME', 'Ime i prezime',
                'ime_i_prezime', 'ime_i_prezime_', '_ime_i_prezime',
                'ime i prezime_', '_ime i prezime'
            ]);
            $email = $this->getValue($row, [
                'e-mail', 'E-MAIL', 'E-mail', 'email',
                'e_mail', 'e-mail_', '_e-mail',
                'email_', '_email'
            ]);
            $clanarina = $this->getValue($row, [
                'članarina', 'ČLANARINA', 'Članarina',
                'članarina_', '_članarina', 'clanarina'
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

            // Email is optional (some rows may not have it)
            if (!empty($email)) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $rowErrors[] = 'E-mail nije valjan.';
                } elseif (in_array(strtolower($email), $this->seenEmails)) {
                    $rowErrors[] = 'E-mail već postoji u ovoj datoteci.';
                }
            }

            if (empty($clanarina)) {
                $rowErrors[] = 'Članarina je obavezna.';
            }

            if (empty($grupa)) {
                $rowErrors[] = 'Grupa je obavezna.';
            }

            // If there are validation errors, skip this row
            if (!empty($rowErrors)) {
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

            // Split full name
            [$firstName, $lastName] = $this->splitFullName($imePrezime);

            // Find member group by name (case-insensitive)
            $memberGroup = MemberGroup::whereRaw('LOWER(name) = ?', [strtolower(trim($grupa))])->first();

            if (!$memberGroup) {
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

            if (!$workshopGroup) {
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

            if (!$workshop) {
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

            // Find membership plan by exact name (case-insensitive, within the workshop)
            $membershipPlan = MembershipPlan::where('workshop_id', $workshop->id)
                ->whereRaw('LOWER(plan) = ?', [strtolower(trim($clanarina))])
                ->first();

            if (!$membershipPlan) {
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

            // Check if email already exists in database (only if email is provided)
            if (!empty($email) && Member::where('email', $email)->exists()) {
                $this->failedCount++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => "E-mail '{$email}' već postoji u bazi podataka.",
                    'data' => [
                        'grupa' => $grupa,
                        'ime_prezime' => $imePrezime,
                        'email' => $email,
                        'clanarina' => $clanarina,
                    ],
                ];
                continue;
            }

            // Create member (with default values for required fields not in spreadsheet)
            try {
                $member = Member::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'date_of_birth' => '2000-01-01', // Default date, can be updated later
                    'phone_number' => 'N/A', // Placeholder, can be updated later
                    'email' => $email ?: $this->generateUniqueEmail($firstName, $lastName), // Generate unique email if empty
                    'invoice_email' => $email ?: null, // Use email if available, otherwise null
                    'is_active' => true,
                ]);

                // Attach member to workshop with membership plan
                $member->workshops()->attach($workshop->id, [
                    'membership_plan_id' => $membershipPlan->id,
                    'membership_start_date' => now(),
                ]);

                // Assign member to the group within this workshop (if not already assigned)
                if (!MemberGroupWorkshop::where('member_id', $member->id)
                    ->where('workshop_id', $workshop->id)
                    ->exists()) {
                    MemberGroupWorkshop::create([
                        'member_id' => $member->id,
                        'workshop_id' => $workshop->id,
                        'member_group_id' => $memberGroup->id,
                    ]);
                }

                $this->createdCount++;
                $this->seenEmails[] = strtolower($email);
            } catch (\Exception $e) {
                $this->failedCount++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Greška pri kreiranju člana: ' . $e->getMessage(),
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
     * Get value from row by trying multiple header variations
     */
    private function getValue(Collection $row, array $keys): ?string
    {
        // First, let's see what keys are actually available (for debugging)
        $availableKeys = $row->keys()->toArray();
        
        foreach ($keys as $key) {
            // Try exact key
            if (isset($row[$key])) {
                $value = $row[$key];
                return is_null($value) ? null : trim((string) $value);
            }

            // Try case-insensitive match (normalize both for comparison)
            foreach ($row->keys() as $rowKey) {
                // Normalize both keys: lowercase, remove special chars, normalize spaces
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
        $base = strtolower(Str::ascii($firstName . '.' . $lastName));
        $base = preg_replace('/[^a-z0-9]/', '', $base);
        $base = substr($base, 0, 20); // Limit length
        
        $email = $base . '@import.local';
        $counter = 1;
        
        // Ensure uniqueness
        while (Member::where('email', $email)->exists()) {
            $email = $base . $counter . '@import.local';
            $counter++;
        }
        
        return $email;
    }

    /**
     * Split full name by last space into first_name and last_name
     */
    private function splitFullName(string $fullName): array
    {
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

    /**
     * Get import results
     */
    public function getResults(): array
    {
        return [
            'created_count' => $this->createdCount,
            'failed_count' => $this->failedCount,
            'errors' => $this->errors,
        ];
    }
}

