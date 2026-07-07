<?php

declare(strict_types=1);

return [
    'name' => getenv('HOTEL_NAME') ?: 'Emperor Hotel',
    'description' => getenv('HOTEL_DESCRIPTION') ?: 'Emperor Hotel Reservation and Management System',
    'founded_year' => filter_var(getenv('HOTEL_FOUNDED_YEAR'), FILTER_VALIDATE_INT) ?: null,
    'founded_note' => getenv('HOTEL_FOUNDED_NOTE') ?: 'Founding year is not recorded in the current project yet.',
    'support_email' => getenv('SUPPORT_EMAIL') ?: '',
    'support_phone' => getenv('SUPPORT_PHONE') ?: '',
];
