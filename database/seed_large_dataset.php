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

    // 1. Seed 50 Users (Admin + Staff + Registered Guests)
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $userPasswordHash = password_hash('user123', PASSWORD_DEFAULT);

    $usersData = [
        ['full_name' => 'Jeffrey U. Pantaleon Jr.', 'email' => 'jayjaypantaleon@gmail.com', 'role' => 'admin'],
        ['full_name' => 'Wizard Of Xerox', 'email' => 'wizardofxerox@gmail.com', 'role' => 'user'],
        ['full_name' => 'Vincent Gabriel', 'email' => 'vincent@gmail.com', 'role' => 'user'],
        ['full_name' => 'Lore Mae Reyes', 'email' => 'lore@gmail.com', 'role' => 'user'],
        ['full_name' => 'Maria Santos', 'email' => 'maria.santos@gmail.com', 'role' => 'user'],
        ['full_name' => 'Juan Dela Cruz', 'email' => 'juan.delacruz@gmail.com', 'role' => 'user'],
        ['full_name' => 'Alexander Reyes', 'email' => 'alex.reyes@gmail.com', 'role' => 'user'],
        ['full_name' => 'Sarah Tan', 'email' => 'sarah.tan@gmail.com', 'role' => 'user'],
        ['full_name' => 'David Lim', 'email' => 'david.lim@gmail.com', 'role' => 'user'],
        ['full_name' => 'Carmen Aquino', 'email' => 'carmen.aquino@gmail.com', 'role' => 'user'],
        ['full_name' => 'Marco Villa', 'email' => 'marco.villa@gmail.com', 'role' => 'user'],
        ['full_name' => 'Elena Gomez', 'email' => 'elena.gomez@gmail.com', 'role' => 'user'],
        ['full_name' => 'Robert Chen', 'email' => 'robert.chen@gmail.com', 'role' => 'user'],
        ['full_name' => 'Patricia Flores', 'email' => 'patricia.flores@gmail.com', 'role' => 'user'],
        ['full_name' => 'Michael Sy', 'email' => 'michael.sy@gmail.com', 'role' => 'user'],
        ['full_name' => 'Jennifer Ramos', 'email' => 'jennifer.ramos@gmail.com', 'role' => 'user'],
        ['full_name' => 'Richard Mendoza', 'email' => 'richard.mendoza@gmail.com', 'role' => 'user'],
        ['full_name' => 'Emily Cruz', 'email' => 'emily.cruz@gmail.com', 'role' => 'user'],
        ['full_name' => 'Daniel Torres', 'email' => 'daniel.torres@gmail.com', 'role' => 'user'],
        ['full_name' => 'Stephanie Bautista', 'email' => 'stephanie.bautista@gmail.com', 'role' => 'user'],
        ['full_name' => 'Christopher Santiago', 'email' => 'christopher.santiago@gmail.com', 'role' => 'user'],
        ['full_name' => 'Amanda Castillo', 'email' => 'amanda.castillo@gmail.com', 'role' => 'user'],
        ['full_name' => 'James Navarro', 'email' => 'james.navarro@gmail.com', 'role' => 'user'],
        ['full_name' => 'Elizabeth Villanueva', 'email' => 'elizabeth.villanueva@gmail.com', 'role' => 'user'],
        ['full_name' => 'Anthony Garcia', 'email' => 'anthony.garcia@gmail.com', 'role' => 'user'],
        ['full_name' => 'Hannah Diaz', 'email' => 'hannah.diaz@gmail.com', 'role' => 'user'],
        ['full_name' => 'Jason Valdez', 'email' => 'jason.valdez@gmail.com', 'role' => 'user'],
        ['full_name' => 'Rachel Salazar', 'email' => 'rachel.salazar@gmail.com', 'role' => 'user'],
        ['full_name' => 'Brian Soriano', 'email' => 'brian.soriano@gmail.com', 'role' => 'user'],
        ['full_name' => 'Megan Delos Reyes', 'email' => 'megan.delosreyes@gmail.com', 'role' => 'user'],
        ['full_name' => 'Patrick Ong', 'email' => 'patrick.ong@gmail.com', 'role' => 'user'],
        ['full_name' => 'Grace Chiu', 'email' => 'grace.chiu@gmail.com', 'role' => 'user'],
        ['full_name' => 'Mark Anthony Co', 'email' => 'mark.co@gmail.com', 'role' => 'user'],
        ['full_name' => 'Angela Dimaculangan', 'email' => 'angela.dimaculangan@gmail.com', 'role' => 'user'],
        ['full_name' => 'Ramon Roxas', 'email' => 'ramon.roxas@gmail.com', 'role' => 'user'],
        ['full_name' => 'Katrina Legaspi', 'email' => 'katrina.legaspi@gmail.com', 'role' => 'user'],
        ['full_name' => 'Francis Aranas', 'email' => 'francis.aranas@gmail.com', 'role' => 'user'],
        ['full_name' => 'Bea Alonzo', 'email' => 'bea.alonzo@gmail.com', 'role' => 'user'],
        ['full_name' => 'Gerald Anderson', 'email' => 'gerald.anderson@gmail.com', 'role' => 'user'],
        ['full_name' => 'Janine Gutierrez', 'email' => 'janine.gutierrez@gmail.com', 'role' => 'user']
    ];

    $stmtUser = $db->prepare("INSERT INTO users (full_name, email, password_hash, role, email_verified) VALUES (:full_name, :email, :hash, :role, 1)");
    $userMap = [];

    foreach ($usersData as $u) {
        $hash = $u['role'] === 'admin' ? $passwordHash : $userPasswordHash;
        $stmtUser->execute([
            'full_name' => $u['full_name'],
            'email' => $u['email'],
            'hash' => $hash,
            'role' => $u['role']
        ]);
        $userMap[$u['email']] = (int)$db->lastInsertId();
    }
    echo "Inserted " . count($userMap) . " registered users.\n";

    // 2. Seed Guests (60+ Guests)
    $stmtGuest = $db->prepare("INSERT INTO guests (user_id, first_name, last_name, phone, email) VALUES (:user_id, :first_name, :last_name, :phone, :email)");
    $guestIds = [];
    $phonePrefixes = ['0917', '0918', '0928', '0908', '0977', '0956', '0998', '0915', '0920', '0939'];

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

    // Add 25 Walk-in Guests (no user_id)
    $walkinNames = [
        ['Gabriel', 'Alvarez'], ['Sophia', 'Morales'], ['Justin', 'Enriquez'], ['Isabella', 'Pineda'],
        ['Kenneth', 'Ocampo'], ['Victoria', 'Dizon'], ['Christian', 'Aguilar'], ['Samantha', 'Vergara'],
        ['Raymond', 'Pascual'], ['Veronica', 'Tolentino'], ['Nathaniel', 'Bermudez'], ['Chloe', 'David'],
        ['Julian', 'Corpus'], ['Camilla', 'Serrano'], ['Ethan', 'Zamora'], ['Dominic', 'Paredes'],
        ['Jasmine', 'Fernandez'], ['Leo', 'Nolasco'], ['Bianca', 'Sison'], ['Paolo', 'Macaraeg'],
        ['Kristine', 'Bernardo'], ['Enrique', 'Gil'], ['Liza', 'Soberano'], ['Daniel', 'Padilla'], ['Kathryn', 'Bernardo']
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

    // 3. Ensure Rooms list
    $roomsList = $db->query("SELECT room_id, room_number, room_type, price_per_night FROM rooms ORDER BY room_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (count($roomsList) === 0) {
        echo "No rooms found! Seed database/schema.sql first.\n";
        exit(1);
    }

    // Reset room statuses to Available first
    $db->exec("UPDATE rooms SET status = 'Available'");

    // 4. MASSIVE RESERVATIONS GENERATION (220+ Reservations)
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

    $paymentMethods = ['Credit Card', 'E-Wallet', 'Bank Transfer', 'Cash', 'Debit Card'];

    $reviewsTemplates = [
        ["rating" => 5, "comment" => "Absolute 5-star luxury! The room exceeded all our expectations. Impeccable cleanliness and ultra-responsive butler service."],
        ["rating" => 5, "comment" => "Beautiful view of the city skyline and extremely comfortable king bed. Will definitely book again!"],
        ["rating" => 5, "comment" => "Sublime stay at Emperor Hotel. The smart lighting controls and high-speed Wi-Fi made our executive trip seamless."],
        ["rating" => 5, "comment" => "Top tier hospitality! Breakfast spread was world-class and the room amenities were top notch."],
        ["rating" => 4, "comment" => "Wonderful experience from check-in to check-out. Very quiet, elegant, and peaceful suite."],
        ["rating" => 5, "comment" => "Everything was pristine. The marble bathroom and rain shower felt like a private spa resort."],
        ["rating" => 5, "comment" => "Exceeded expectations! Smooth check-in process and staff went above and beyond."],
        ["rating" => 5, "comment" => "The suite layout and panoramic windows are breathtaking. Worth every peso!"],
        ["rating" => 4, "comment" => "Very high-end facilities and spacious living area. Room service was fast and courteous."],
        ["rating" => 5, "comment" => "Best luxury hotel experience in the city! Cleanliness is 10/10."],
        ["rating" => 4, "comment" => "Great ambiance and delicious food options. Check-out was effortless."],
        ["rating" => 5, "comment" => "The Emperor Presidential suite is spectacular! Unmatched elegance."]
    ];

    $resCount = 0;
    $payCount = 0;
    $revCount = 0;

    // A. Past Stays (150+ Checked-out Reservations from 2025 to June 2026)
    for ($i = 0; $i < 160; $i++) {
        $room = $roomsList[$i % count($roomsList)];
        $gId = $guestIds[$i % count($guestIds)];

        $uId = $db->query("SELECT user_id FROM guests WHERE guest_id = $gId")->fetchColumn();
        $uId = $uId ? (int)$uId : null;

        // Generate date in past (10 to 365 days ago)
        $daysAgo = rand(10, 365);
        $stayNights = rand(1, 5);

        $checkInDate = date('Y-m-d', strtotime("-$daysAgo days"));
        $checkOutDate = date('Y-m-d', strtotime("-$daysAgo days + $stayNights days"));
        $createdAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days - " . rand(2, 14) . " days"));

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
        $txRef = strtoupper(substr($method, 0, 3)) . '-' . date('Ymd', strtotime($createdAt)) . '-' . rand(10000, 99999);
        $stmtPay->execute([
            'reservation_id' => $resId,
            'amount' => $totalAmount,
            'payment_method' => $method,
            'payment_status' => 'Paid',
            'transaction_reference' => $txRef,
            'payment_date' => date('Y-m-d H:i:s', strtotime($createdAt . ' + 2 hours'))
        ]);
        $payCount++;

        // Add Room Review for user bookings (70% probability)
        if ($uId && rand(1, 10) <= 7) {
            $revTpl = $reviewsTemplates[rand(0, count($reviewsTemplates) - 1)];
            $stmtRev->execute([
                'reservation_id' => $resId,
                'user_id' => $uId,
                'room_id' => $room['room_id'],
                'rating' => $revTpl['rating'],
                'comment' => $revTpl['comment'],
                'created_at' => date('Y-m-d H:i:s', strtotime($checkOutDate . ' + 1 day'))
            ]);
            $revCount++;
        }
    }

    // B. Currently Active Checked-in Stays (12 Rooms Currently Occupied)
    $occupiedRoomNumbers = ['101', '105', '109', '201', '204', '208', '211', '301', '304', '307', '310', '311'];
    foreach ($occupiedRoomNumbers as $rNum) {
        $room = $db->query("SELECT room_id, price_per_night FROM rooms WHERE room_number = '$rNum'")->fetch(PDO::FETCH_ASSOC);
        if (!$room) continue;

        $gId = $guestIds[array_rand($guestIds)];
        $uId = $db->query("SELECT user_id FROM guests WHERE guest_id = $gId")->fetchColumn();
        $uId = $uId ? (int)$uId : null;

        $checkInDate = date('Y-m-d', strtotime('-1 days'));
        $checkOutDate = date('Y-m-d', strtotime('+3 days'));
        $totalAmount = (float)$room['price_per_night'] * 4;

        $stmtRes->execute([
            'user_id' => $uId,
            'guest_id' => $gId,
            'room_id' => $room['room_id'],
            'check_in' => $checkInDate,
            'check_out' => $checkOutDate,
            'total_amount' => $totalAmount,
            'status' => 'Checked-in',
            'created_at' => date('Y-m-d H:i:s', strtotime('-4 days'))
        ]);
        $resId = (int)$db->lastInsertId();
        $resCount++;

        $method = $paymentMethods[rand(0, count($paymentMethods) - 1)];
        $stmtPay->execute([
            'reservation_id' => $resId,
            'amount' => $totalAmount,
            'payment_method' => $method,
            'payment_status' => 'Paid',
            'transaction_reference' => 'CHK-' . date('Ymd') . '-' . rand(10000, 99999),
            'payment_date' => date('Y-m-d H:i:s', strtotime('-4 days + 1 hour'))
        ]);
        $payCount++;

        // Mark Room Occupied
        $db->exec("UPDATE rooms SET status = 'Occupied' WHERE room_id = " . $room['room_id']);
    }

    // C. Upcoming Confirmed Reservations (10 Rooms Reserved)
    $reservedRoomNumbers = ['102', '106', '110', '202', '205', '209', '302', '305', '308', '309'];
    foreach ($reservedRoomNumbers as $rNum) {
        $room = $db->query("SELECT room_id, price_per_night FROM rooms WHERE room_number = '$rNum'")->fetch(PDO::FETCH_ASSOC);
        if (!$room) continue;

        $gId = $guestIds[array_rand($guestIds)];
        $uId = $db->query("SELECT user_id FROM guests WHERE guest_id = $gId")->fetchColumn();
        $uId = $uId ? (int)$uId : null;

        $daysAhead = rand(2, 15);
        $stayNights = rand(2, 4);

        $checkInDate = date('Y-m-d', strtotime("+$daysAhead days"));
        $checkOutDate = date('Y-m-d', strtotime("+$daysAhead days + $stayNights days"));
        $totalAmount = (float)$room['price_per_night'] * $stayNights;

        $stmtRes->execute([
            'user_id' => $uId,
            'guest_id' => $gId,
            'room_id' => $room['room_id'],
            'check_in' => $checkInDate,
            'check_out' => $checkOutDate,
            'total_amount' => $totalAmount,
            'status' => 'Confirmed',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ]);
        $resId = (int)$db->lastInsertId();
        $resCount++;

        $method = $paymentMethods[rand(0, count($paymentMethods) - 1)];
        $stmtPay->execute([
            'reservation_id' => $resId,
            'amount' => $totalAmount,
            'payment_method' => $method,
            'payment_status' => 'Confirmed',
            'transaction_reference' => 'RES-' . date('Ymd') . '-' . rand(10000, 99999),
            'payment_date' => date('Y-m-d H:i:s', strtotime('-2 days + 30 mins'))
        ]);
        $payCount++;

        // Mark Room Reserved
        $db->exec("UPDATE rooms SET status = 'Reserved' WHERE room_id = " . $room['room_id']);
    }

    // D. Pending Reservation Requests (15 Bookings)
    for ($p = 0; $p < 15; $p++) {
        $room = $roomsList[rand(0, count($roomsList) - 1)];
        $gId = $guestIds[array_rand($guestIds)];
        $uId = $db->query("SELECT user_id FROM guests WHERE guest_id = $gId")->fetchColumn();
        $uId = $uId ? (int)$uId : null;

        $daysAhead = rand(5, 30);
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
            'status' => 'Pending',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 days'))
        ]);
        $resId = (int)$db->lastInsertId();
        $resCount++;

        $method = $paymentMethods[rand(0, count($paymentMethods) - 1)];
        $stmtPay->execute([
            'reservation_id' => $resId,
            'amount' => $totalAmount,
            'payment_method' => $method,
            'payment_status' => 'Pending',
            'transaction_reference' => 'PND-' . date('Ymd') . '-' . rand(10000, 99999),
            'payment_date' => date('Y-m-d H:i:s', strtotime('-1 days'))
        ]);
        $payCount++;
    }

    // E. Cancelled Reservations (15 Bookings)
    for ($c = 0; $c < 15; $c++) {
        $room = $roomsList[rand(0, count($roomsList) - 1)];
        $gId = $guestIds[array_rand($guestIds)];
        $uId = $db->query("SELECT user_id FROM guests WHERE guest_id = $gId")->fetchColumn();
        $uId = $uId ? (int)$uId : null;

        $daysAgo = rand(5, 60);
        $checkInDate = date('Y-m-d', strtotime("+$daysAgo days"));
        $checkOutDate = date('Y-m-d', strtotime("+$daysAgo days + 2 days"));
        $totalAmount = (float)$room['price_per_night'] * 2;

        $stmtRes->execute([
            'user_id' => $uId,
            'guest_id' => $gId,
            'room_id' => $room['room_id'],
            'check_in' => $checkInDate,
            'check_out' => $checkOutDate,
            'total_amount' => $totalAmount,
            'status' => 'Cancelled',
            'created_at' => date('Y-m-d H:i:s', strtotime("-$daysAgo days"))
        ]);
        $resId = (int)$db->lastInsertId();
        $resCount++;

        $stmtPay->execute([
            'reservation_id' => $resId,
            'amount' => $totalAmount,
            'payment_method' => 'Credit Card',
            'payment_status' => 'Refunded',
            'transaction_reference' => 'REF-' . date('Ymd') . '-' . rand(10000, 99999),
            'payment_date' => date('Y-m-d H:i:s', strtotime("-$daysAgo days + 1 day"))
        ]);
        $payCount++;
    }

    // F. Cleaning & Maintenance Rooms
    $db->exec("UPDATE rooms SET status = 'Cleaning' WHERE room_number IN ('103', '203')");
    $db->exec("UPDATE rooms SET status = 'Maintenance' WHERE room_number IN ('312')");

    echo "Inserted $resCount total reservations.\n";
    echo "Inserted $payCount total payment transactions.\n";
    echo "Inserted $revCount total verified guest room reviews.\n";

    echo "MASSIVE DATABASE SEEDING COMPLETED SUCCESSFULLY!\n";

} catch (Throwable $e) {
    echo "ERROR during data seed: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
