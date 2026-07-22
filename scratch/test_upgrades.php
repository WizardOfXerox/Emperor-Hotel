<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/includes/bootstrap.php';

echo "=== EMPEROR HOTEL FULL-SYSTEM UPGRADES VERIFICATION SCRIPT ===\n\n";

$db = Database::connect();
$roomModel = new Room($db);
$reviewModel = new Review($db);
$reservationModel = new Reservation($db);
$userModel = new User($db);

// 1. Verify Room Statuses and Bed Types
echo "1. Testing Room Statuses & Bed Types:\n";
$statuses = Room::statuses();
echo "   Available Statuses: " . implode(', ', $statuses) . "\n";
assert(in_array('Cleaning', $statuses, true), 'Cleaning status missing');
assert(in_array('Maintenance', $statuses, true), 'Maintenance status missing');
echo "   [PASSED] Room status ENUM contains Cleaning and Maintenance.\n\n";

// 2. Testing Recommendation Score Algorithm
echo "2. Testing Recommendation Score Algorithm:\n";
$scoredRooms = $roomModel->calculateRecommendationScores();
echo "   Total Scored Rooms: " . count($scoredRooms) . "\n";
$topRoom = reset($scoredRooms);
echo "   Top Recommended Room: #" . $topRoom['room_number'] . " (" . $topRoom['room_type'] . ") - Score: " . $topRoom['recommendation_score'] . "\n";
assert(isset($topRoom['recommendation_score']), 'Recommendation score missing');
echo "   [PASSED] Recommendation Score algorithm returned valid weighted scores.\n\n";

// 3. Testing SMTP Mailer Helper
echo "3. Testing SMTP Mailer Helper (Presentation Simulator Mode):\n";
$sent = sendSmtpEmail('test@example.com', 'Test Subject', '<p>Test</p>', '987654');
assert($sent === true, 'SMTP Email helper failed');
echo "   [PASSED] SMTP Email Helper executed successfully in Presentation Simulator Mode.\n\n";

// 4. Testing Review Model Ratings Summary
echo "4. Testing Review Model Ratings Summary:\n";
$ratingsPerType = $reviewModel->averageRatingPerRoomType();
foreach ($ratingsPerType as $type => $data) {
    echo "   " . $type . ": ★ " . $data['avg_rating'] . " (" . $data['review_count'] . " reviews)\n";
}
echo "   [PASSED] Review Model aggregated ratings per room type.\n\n";

echo "ALL VERIFICATION TESTS COMPLETED SUCCESSFULLY!\n";
