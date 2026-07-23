<?php

declare(strict_types=1);

return [
    'name' => getenv('HOTEL_NAME') ?: ($_ENV['HOTEL_NAME'] ?? 'Emperor Hotel'),
    'description' => getenv('HOTEL_DESCRIPTION') ?: ($_ENV['HOTEL_DESCRIPTION'] ?? 'Emperor Hotel Reservation and Management System'),
    'address' => getenv('HOTEL_ADDRESS') ?: ($_ENV['HOTEL_ADDRESS'] ?? 'Royal Bay Boulevard, Cultural Center Complex, Metro Manila 1000, Philippines'),
    'founded_year' => filter_var(getenv('HOTEL_FOUNDED_YEAR') ?: ($_ENV['HOTEL_FOUNDED_YEAR'] ?? null), FILTER_VALIDATE_INT) ?: null,
    'founded_note' => getenv('HOTEL_FOUNDED_NOTE') ?: ($_ENV['HOTEL_FOUNDED_NOTE'] ?? 'Founding year is not recorded in the current project yet.'),
    'support_email' => getenv('SUPPORT_EMAIL') ?: ($_ENV['SUPPORT_EMAIL'] ?? 'concierge@emperorshotel.com'),
    'support_phone' => getenv('SUPPORT_PHONE') ?: ($_ENV['SUPPORT_PHONE'] ?? '+63 942 459 0845'),
];
