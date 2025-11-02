<?php

return [
    // Recipient (PRIMATELJ)
    'recipient_name'    => env('PONTES_RECIPIENT_NAME', 'Udruga Pontes'),
    'recipient_address' => env('PONTES_RECIPIENT_ADDRESS', 'Rijeka, Hrvatska'),
    'recipient_postal' => env('PONTES_RECIPIENT_POSTAL', '51000, Rijeka'),
    'recipient_iban'    => env('PONTES_RECIPIENT_IBAN', 'HR00 0000 0000 0000 0000 0'),
    // Payment model + reference (Poziv na broj primatelja)
    'model'             => env('PONTES_MODEL', 'HR00'),
    // Currency
    'currency'          => env('PONTES_CURRENCY', 'EUR'),
];
