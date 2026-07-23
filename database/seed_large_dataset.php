<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/includes/bootstrap.php';

try {
    $db = Database::connect();
    echo "Connected to emperors_hotel_db successfully.\n";

    // Disable foreign key checks for clean re-seeding
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Truncate existing transactional tables
    $db->exec("TRUNCATE TABLE room_reviews");
    $db->exec("TRUNCATE TABLE payments");
    $db->exec("TRUNCATE TABLE reservations");
    $db->exec("TRUNCATE TABLE guests");
    $db->exec("TRUNCATE TABLE users");

    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "Cleared existing transaction and user tables.\n";

    // 1. Seed Users (Admin + Staff + Guests)
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $userPasswordHash = password_hash('user123', PASSWORD_DEFAULT);

    $usersData = [
        ['full_name' => 'Jeffrey U. Pantaleon Jr.', 'email' => 'jayjaypantaleon@gmail.com', 'hash' => $passwordHash, 'role' => 'admin'],
        ['full_name' => 'Wizard Of Xerox', 'email' => 'wizardofxerox@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Vincent Gabriel', 'email' => 'vincent@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Lore Mae Reyes', 'email' => 'lore@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Maria Santos', 'email' => 'maria.santos@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Juan Dela Cruz', 'email' => 'juan.delacruz@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Alexander Reyes', 'email' => 'alex.reyes@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Sarah Tan', 'email' => 'sarah.tan@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'David Lim', 'email' => 'david.lim@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Carmen Aquino', 'email' => 'carmen.aquino@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Marco Villa', 'email' => 'marco.villa@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Elena Gomez', 'email' => 'elena.gomez@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Robert Chen', 'email' => 'robert.chen@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Patricia Flores', 'email' => 'patricia.flores@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Michael Sy', 'email' => 'michael.sy@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Jennifer Ramos', 'email' => 'jennifer.ramos@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Richard Mendoza', 'email' => 'richard.mendoza@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Emily Cruz', 'email' => 'emily.cruz@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Daniel Torres', 'email' => 'daniel.torres@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Stephanie Bautista', 'email' => 'stephanie.bautista@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Christopher Santiago', 'email' => 'christopher.santiago@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Amanda Castillo', 'email' => 'amanda.castillo@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'James Navarro', 'email' => 'james.navarro@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Elizabeth Villanueva', 'email' => 'elizabeth.villanueva@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Anthony Garcia', 'email' => 'anthony.garcia@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Hannah Diaz', 'email' => 'hannah.diaz@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Jason Valdez', 'email' => 'jason.valdez@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Rachel Salazar', 'email' => 'rachel.salazar@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Brian Soriano', 'email' => 'brian.soriano@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
        ['full_name' => 'Megan Delos Reyes', 'email' => 'megan.delosreyes@gmail.com', 'hash' => $userPasswordHash, 'role' => 'user'],
    ];

    $stmtUser = $db->prepare("INSERT INTO users (full_name, email, password_hash, role, email_verified) VALUES (:full_name, :email, :hash, :role, 1)");
    $userMap = [];

    foreach ($usersData as $u) {
        $stmtUser->execute([
            'full_name' => $u['full_name'],
            'email' => $u['email'],
            'hash' => $u['hash'],
            'role' => $u['role']
        ]);
        $userMap[$u['email']] = (int)$db->lastInsertId();
    }
    echo "Inserted " . count($userMap) . " users.\n";

    // 2. Seed Guests (Linked to Users and Walk-ins)
    $stmtGuest = $db->prepare("INSERT INTO guests (user_id, first_name, last_name, phone, email) VALUES (:user_id, :first_name, :last_name, :phone, :email)");
    $guestIds = [];

    $phonePrefixes = ['0917', '0918', '0928', '0908', '0977', '0956', '0998'];

    foreach ($usersData as $idx => $u) {
        $parts = explode(' ', $u['full_name']);
        $firstName = $parts[0];
        $lastName = count($parts) > 1 ? end($parts) : 'Guest';
        $phone = $phonePrefixes[$idx % count($phonePrefixes)] . str_pad((string)rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);

        $stmtGuest->execute([
            'user_id' => $userMap[$u['email']],
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'email' => $u['email']
        ]);
        $guestIds[] = (int)$db->lastInsertId();
    }

    // Add 15 Walk-in Guests (no linked user_id)
    $walkinNames = [
        ['Gabriel', 'Alvarez'], ['Sophia', 'Morales'], ['Justin', 'Enriquez'], ['Isabella', 'Pineda'],
        ['Kenneth', 'Ocampo'], ['Victoria', 'Dizon'], ['Christian', 'Aguilar'], ['Samantha', 'Vergara'],
        ['Raymond', 'Pascual'], ['Veronica', 'Tolentino'], ['Nathaniel', 'Bermudez'], ['Chloe', 'David'],
        ['Julian', 'Corpus'], ['Camilla', 'Serrano'], ['Ethan', 'Zamora']
    ];

    foreach ($walkinNames as $wIdx => $w) {
        $phone = $phonePrefixes[$wIdx % count($phonePrefixes)] . str_pad((string)rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        $email = strtolower($w[0] . '.' . $w[1] . '@yahoo.com');

        $stmtGuest->execute([
            'user_id' => null,
            'first_name' => $w[0],
            'last_name' => $w[1],
            'phone' => $phone,
            'email' => $email
        ]);
        $guestIds[] = (int)$db->lastInsertId();
    }
    echo "Inserted " . count($guestIds) . " guests.\n";

    // 3. Ensure Rooms are seeded
    $roomsList = $db->query("SELECT room_id, room_number, room_type, price_per_night FROM rooms ORDER BY room_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (count($roomsList) === 0) {
        echo "No rooms found! Run database/schema.sql first to seed rooms.\n";
        exit(1);
    }
    echo "Found " . count($roomsList) . " rooms in database.\n";

    // 4. Seed 60+ Realistic Reservations across timeline
    $stmtRes = $db->prepare("
        INSERT INTO reservations (user_id, guest_id, room_id, check_in, check_out, total_amount, status, created_at)
        VALUES (:user_id, :guest_id, :room_id, :check_in, :check_out, :total_amount, :status, :created_at)
    ");

    $stmtPay = $db->prepare("
        INSERT INTO payments (reservation_id, amount, payment_method, payment_status, transaction_reference, payment_date)
        VALUES (:reservation_id, :amount, :payment_method, :payment_status, :transaction_reference, :payment_date)
    ");

    $stmtRev = $db->prepare("
        INSERT INTO room_reviews (reservation_id, user_id, room_id, rating, comment, created_at)
        VALUES (:reservation_id, :user_id, :room_id, :rating, :comment, :created_at)
    ");

    $paymentMethods = ['Credit Card', 'GCash', 'Bank Transfer', 'Cash', 'Debit Card'];

    $reviewsTemplates = [
        "Absolute 5-star luxury! The room exceeded all our expectations. Impeccable cleanliness and ultra-responsive butler service.",
        "Beautiful view of the skyline and extremely comfortable king bed. Will definitely book again!",
        "Sublime stay at Emperor Hotel. The smart lighting controls and high-speed Wi-Fi made our executive trip seamless.",
        "Top tier hospitality! Breakfast spread was world-class and the room amenities were top notch.",
        "Wonderful experience from check-in to check-out. Very quiet, elegant, and peaceful suite.",
        "Everything was pristine. The marble bathroom and rain shower felt like a private spa resort.",
        "Exceeded expectations! Smooth check-in process and staff went above and beyond.",
        "The suite layout and panoramic windows are breathtaking. Worth every peso!"
    ];

    $resCount = 0;
    $payCount = 0;
    $revCount = 0;

    // Timeline scenario generator
    // Past reservations (Checked-out)
    for ($i = 1; $i <= 40; $i++) {
        $room = $roomsList[($i - 1) % count($roomsList)];
        $gId = $guestIds[$i % count($guestIds)];

        // Get linked user_id if any
        $uId = $db->query("SELECT user_id FROM guests WHERE guest_id = $gId")->fetchColumn();
        $uId = $uId ? (int)$uId : null;

        $daysAgo = rand(10, 120);
        $stayNights = rand(1, 4);

        $checkInDate = date('Y-m-d', strtotime("-$daysAgo days"));
        $checkOutDate = date('Y-m-d', strtotime("-$daysAgo days + $stayNights days"));
        $createdAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days - 5 days"));

        $totalAmount = (float)$room['price_per_night'] * $stayNights;

        $stmtRes->execute([
            'user_id' => $uId,
            'guest_id' => $gId,
            'room_id' => $room['room_id'],
            'check_in' => $checkInDate,
            'check_out' => $checkOutDate,
            'total_amount' => $totalAmount,
            'status' => 'Checked-out',
            'created_at' => $createdAt
        ]);
        $resId = (int)$db->lastInsertId();
        $resCount++;

        // Add Payment
        $method = $paymentMethods[rand(0, count($paymentMethods) - 1)];
        $txRef = strtoupper(substr($method, 0, 3)) . '-' . date('Ymd', strtotime($createdAt)) . '-' . rand(1000, 9999);
        $stmtPay->execute([
            'reservation_id' => $resId,
            'amount' => $totalAmount,
            'payment_method' => $method,
            'payment_status' => 'Paid',
            'transaction_reference' => $txRef,
            'payment_date' => date('Y-m-d H:i:s', strtotime($createdAt . ' + 1 hour'))
        ]);
        $payCount++;

        // Add Review for user reservations
        if ($uId && rand(0, 1) === 1) {
            $comment = $reviewsTemplates[rand(0, count($reviewsTemplates) - 1)];
            $rating = rand(4, 5);
            $stmtRev->execute([
                'reservation_id' => $resId,
                'user_id' => $uId,
                'room_id' => $room['room_id'],
                'rating' => $rating,
                'comment' => $comment,
                'created_at' => date('Y-m-d H:i:s', strtotime($checkOutDate . ' + 12 hours'))
            ]);
            $revCount++;
        }
    }

    // Active Currently Checked-in Stays (Today: 2026-07-23)
    $activeRooms = [101, 105, 201, 204, 301, 305];
    foreach ($activeRooms as $rNum) {
        $room = $db->query("SELECT room_id, price_per_night FROM rooms WHERE room_number = '$rNum'")->fetch(PDO::FETCH_ASSOC);
        if (!$room) continue;

        $gId = $guestIds[array_rand($guestIds)];
        $uId = $db->query("SELECT user_id FROM guests WHERE guest_id = $gId")->fetchColumn();
        $uId = $uId ? (int)$uId : null;

        $checkInDate = date('Y-m-d', strtotime('-1 days'));
        $checkOutDate = date('Y-m-d', strtotime('+2 days'));
        $totalAmount = (float)$room['price_per_night'] * 3;

        $stmtRes->execute([
            'user_id' => $uId,
            'guest_id' => $gId,
            'room_id' => $room['room_id'],
            'check_in' => $checkInDate,
            'check_out' => $checkOutDate,
            'total_amount' => $totalAmount,
            'status' => 'Checked-in',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
        ]);
        $resId = (int)$db->lastInsertId();
        $resCount++;

        $method = $paymentMethods[rand(0, count($paymentMethods) - 1)];
        $stmtPay->execute([
            'reservation_id' => $resId,
            'amount' => $totalAmount,
            'payment_method' => $method,
            'payment_status' => 'Paid',
            'transaction_reference' => 'CHK-' . date('Ymd') . '-' . rand(1000, 9999),
            'payment_date' => date('Y-m-d H:i:s', strtotime('-1 days'))
        ]);
        $payCount++;

        // Update room status to Occupied
        $db->exec("UPDATE rooms SET status = 'Occupied' WHERE room_id = " . $room['room_id']);
    }

    // Upcoming Confirmed Bookings
    $upcomingRooms = [102, 106, 202, 206, 302, 306];
    foreach ($upcomingRooms as $rNum) {
        $room = $db->query("SELECT room_id, price_per_night FROM rooms WHERE room_number = '$rNum'")->fetch(PDO::FETCH_ASSOC);
        if (!$room) continue;

        $gId = $guestIds[array_rand($guestIds)];
        $uId = $db->query("SELECT user_id FROM guests WHERE guest_id = $gId")->fetchColumn();
        $uId = $uId ? (int)$uId : null;

        $daysAhead = rand(2, 20);
        $checkInDate = date('Y-m-d', strtotime("+$daysAhead days"));
        $checkOutDate = date('Y-m-d', strtotime("+$daysAhead days + 2 days"));
        $totalAmount = (float)$room['price_per_night'] * 2;

        $stmtRes->execute([
            'user_id' => $uId,
            'guest_id' => $gId,
            'room_id' => $room['room_id'],
            'check_in' => $checkInDate,
            'check_out' => $checkOutDate,
            'total_amount' => $totalAmount,
            'status' => 'Confirmed',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 days'))
        ]);
        $resId = (int)$db->lastInsertId();
        $resCount++;

        $method = $paymentMethods[rand(0, count($paymentMethods) - 1)];
        $stmtPay->execute([
            'reservation_id' => $resId,
            'amount' => $totalAmount,
            'payment_method' => $method,
            'payment_status' => 'Confirmed',
            'transaction_reference' => 'RES-' . date('Ymd') . '-' . rand(1000, 9999),
            'payment_date' => date('Y-m-d H:i:s', strtotime('-1 days'))
        ]);
        $payCount++;

        // Update room status to Reserved
        $db->exec("UPDATE rooms SET status = 'Reserved' WHERE room_id = " . $room['room_id']);
    }

    // Cleaning and Maintenance room statuses
    $db->exec("UPDATE rooms SET status = 'Cleaning' WHERE room_number IN ('103', '203')");
    $db->exec("UPDATE rooms SET status = 'Maintenance' WHERE room_number IN ('312')");

    echo "Inserted $resCount reservations.\n";
    echo "Inserted $payCount payments.\n";
    echo "Inserted $revCount room reviews.\n";

    echo "DATABASE DATA SEED COMPLETED SUCCESSFULLY!\n";

} catch (Throwable $e) {
    echo "ERROR during data seed: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
