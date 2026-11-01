<?php

return [
    // Recipient (PRIMATELJ)
    'recipient_name' => env('PONTES_RECIPIENT_NAME', 'Udruga Pontes'),
    'recipient_address' => env('PONTES_RECIPIENT_ADDRESS', 'Rijeka, Hrvatska'),
    'recipient_postal' => env('PONTES_RECIPIENT_POSTAL', '51000, Rijeka'),
    'recipient_iban' => env('PONTES_RECIPIENT_IBAN', 'HR00 0000 0000 0000 0000 0'),
    // Payment model + reference (Poziv na broj primatelja)
    'model' => env('PONTES_MODEL', 'HR00'),
    // Currency
    'currency' => env('PONTES_CURRENCY', 'EUR'),
    // Contact details
    'web_url' => env('PONTES_WEB_URL', ''),
    'email' => env('PONTES_EMAIL', ''),
    'phone' => env('PONTES_PHONE', ''),
    // Days after due_date before the dashboard suggests a reminder. Sending itself is not gated by this.
    'reminder_grace_days' => (int) env('PONTES_REMINDER_GRACE_DAYS', 10),
];
