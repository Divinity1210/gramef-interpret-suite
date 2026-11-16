<?php

return [
    // Default bank details used in invoice footers
    'account_name' => env('BANK_ACCOUNT_NAME', 'A TO Z INTERPRETING & TRANSLATION SERVICES'),
    'sort_code' => env('BANK_SORT_CODE', '30-65-49'),
    'account_number' => env('BANK_ACCOUNT_NUMBER', '42914660'),
    'notes' => env('BANK_NOTES', 'Please note our bank details. Invoice payments should be made to this account.'),
    'vat_number' => env('BANK_VAT_NUMBER', '925237623'),
];
