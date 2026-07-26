<?php

declare(strict_types=1);

require_once APP_ROOT . '/public/includes/room_catalog.php';

class SupportAssistant
{
    private array $hotelProfile;
    private Room $roomModel;
    private Reservation $reservationModel;
    private Payment $paymentModel;

    public function __construct(private PDO $db)
    {
        $this->hotelProfile = require APP_ROOT . '/app/config/hotel.php';
        $this->roomModel = new Room($db);
        $this->reservationModel = new Reservation($db);
        $this->paymentModel = new Payment($db);
    }

    public function respond(string $message, string $scope, array $history = [], array $keywords = []): array
    {
        $scope = $this->normalizeScope($scope);
        $normalized = $this->normalizeText($message);
        $contextText = $this->buildContextText($message, $history, $keywords);
        $range = $this->extractDateRange($normalized) ?? $this->extractDateRange($contextText) ?? $this->defaultRange();
        $isGreeting = $this->isGreeting($normalized);
        $customerIntent = $this->hasCustomerIntent($normalized);
        $adminIntent = $this->hasAdminIntent($normalized);
        $datasetMatches = $this->findAllDatasetMatches($normalized);

        if ($isGreeting) {
            return $this->greetingReply($scope);
        }

        if (!empty($datasetMatches)) {
            return $this->datasetMultiReply($datasetMatches);
        }

        if ($scope === 'admin') {
            if ($this->matchesAny($normalized, ['most booked room', 'room number', 'which room number', 'what room number', 'top room number', 'popular room', 'best room number', 'most booked'])) {
                return $this->adminMostBookedRoomReply($range);
            }

            if ($this->matchesAny($normalized, ['housekeeping', 'cleaning', 'dirty rooms', 'dirty room', 'maintenance'])) {
                return $this->adminHousekeepingReply();
            }

            if ($this->matchesAny($normalized, ['alos', 'lead time', 'revpar', 'adr', 'forecast', 'cancellation loss', 'loyalty', 'repeat guest', 'executive'])) {
                return $this->adminExecutiveForecastReply();
            }

            if ($this->isStatisticsIntent($normalized)) {
                return $this->adminStatisticsReply($normalized, $range);
            }

            if ($adminIntent) {
                if ($this->matchesAny($normalized, ['monthly sales', 'sales', 'revenue', 'income', 'monthly report'])) {
                    return $this->adminSalesReply($range);
                }

                if ($this->matchesAny($normalized, ['occupancy', 'booking trend', 'reservation trend', 'reservations', 'payments', 'alerts', 'room stats'])) {
                    return $this->adminOperationsReply($range);
                }

                return $this->adminOverviewReply($range);
            }

            if ($customerIntent) {
                $roomTypeMatch = $this->matchesAny($normalized, ['room type', 'room types', 'types of rooms', 'type of room', 'room categories', 'room categories and prices']);
                $roomAvailabilityMatch = $this->matchesAny($normalized, ['available rooms', 'room availability', 'rooms available', 'available room']);
                $roomPricingMatch = $this->matchesAny($normalized, ['room price', 'room prices', 'room rate', 'room rates', 'price per night']);

                if ($roomTypeMatch && !$roomAvailabilityMatch && !$roomPricingMatch) {
                    return $this->customerRoomTypeReply();
                }

                if ($roomAvailabilityMatch && !$roomTypeMatch) {
                    return $this->customerAvailabilityReply();
                }

                if ($roomPricingMatch) {
                    return $this->customerRoomPriceReply();
                }

                if ($this->matchesAny($normalized, ['found', 'founded', 'founding', 'history', 'established', 'running', 'about emperor hotel', 'about the hotel'])) {
                    return $this->hotelProfileReply();
                }

                if ($this->matchesAny($normalized, ['booking', 'reservation', 'check in', 'check out', 'payment'])) {
                    return $this->customerBookingReply();
                }
            }

            return $this->aiReply($scope, $range, $keywords);
        }

        if ($this->matchesAny($normalized, ['receipt', 'receipts', 'my booking', 'reservation status', 'my receipt', 'booking status', 'lookup reservation', 'check my', 'find booking', 'view booking', 'view reservation', 'lookup', 'status'])) {
            return $this->customerReservationLookupReply();
        }

        if ($this->matchesAny($normalized, ['spa', 'dining', 'pool', 'breakfast', 'towel', 'pillows', 'shuttle', 'amenities', 'menu'])) {
            return $this->customerConciergeServicesReply();
        }

        if ($customerIntent) {
            $roomTypeMatch = $this->matchesAny($normalized, ['room type', 'room types', 'types of rooms', 'type of room', 'room categories', 'room categories and prices']);
            $roomAvailabilityMatch = $this->matchesAny($normalized, ['available rooms', 'room availability', 'rooms available', 'available room']);
            $roomPricingMatch = $this->matchesAny($normalized, ['room price', 'room prices', 'room rate', 'room rates', 'price per night']);

            if ($roomTypeMatch && !$roomAvailabilityMatch && !$roomPricingMatch) {
                return $this->customerRoomTypeReply();
            }

            if ($roomAvailabilityMatch && !$roomTypeMatch) {
                return $this->customerAvailabilityReply();
            }

            if ($roomPricingMatch) {
                return $this->customerRoomPriceReply();
            }

            if ($this->matchesAny($normalized, ['found', 'founded', 'founding', 'history', 'established', 'running', 'about emperor hotel', 'about the hotel'])) {
                return $this->hotelProfileReply();
            }

            if ($this->matchesAny($normalized, ['booking', 'reservation', 'check in', 'check out', 'payment'])) {
                return $this->customerBookingReply();
            }
        }

        return $this->aiReply($scope, $range, $keywords);
    }

    private function customerAvailabilityReply(): array
    {
        $availableRooms = $this->roomModel->availableRooms();

        if (!$availableRooms) {
            $html = "<div style='background: rgba(15,23,42,0.9); border: 1px solid rgba(212,175,55,0.4); border-radius: 8px; padding: 10px; text-align: center;'>
                        <strong style='color:#ffdf73; font-size:12px;'>All Rooms Fully Booked</strong>
                        <p style='font-size:11px; color:#94a3b8; margin: 3px 0 0 0;'>No vacant rooms available right now.</p>
                     </div>";
            return [
                'text' => $html,
                'kind' => 'customer-availability',
                'quick_chips' => ['💰 Suite Rates', '🏨 Room Categories', '🛎️ Concierge Desk'],
            ];
        }

        $html = "<div style='margin-bottom:6px; font-weight:bold; color:#ffdf73; font-size:13px;'>🏨 Available Rooms (" . count($availableRooms) . " Ready):</div>";
        $html .= "<div style='display:flex; flex-direction:column; gap:6px;'>";

        foreach (array_slice($availableRooms, 0, 5) as $room) {
            $priceFormatted = formatMoney((float) $room['price_per_night']);
            $roomId = (int) $room['room_id'];
            $roomNum = htmlspecialchars((string) $room['room_number']);
            $suiteType = htmlspecialchars((string) $room['room_type']);
            $floor = (int) $room['floor'];

            $html .= "
            <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.35); border-radius: 8px; padding: 7px 10px;'>
                <div style='display:flex; justify-content:space-between; align-items:center;'>
                    <span style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:13px;'>Room #{$roomNum} &bull; {$suiteType}</span>
                    <span style='background:rgba(34,197,94,0.2); color:#4ade80; border:1px solid rgba(34,197,94,0.4); padding:1px 6px; border-radius:99px; font-size:10px; font-weight:bold;'>Available</span>
                </div>
                <div style='display:flex; justify-content:space-between; align-items:center; margin-top:4px;'>
                    <span style='font-size:11px; color:#cbd5e1;'>Floor {$floor} &bull; <strong style='color:#ffdf73;'>{$priceFormatted}</strong>/night</span>
                    <a href='../user/dashboard.php?room_id={$roomId}' style='background:linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color:#020617; font-weight:bold; padding:3px 10px; border-radius:6px; text-decoration:none; font-size:11px;'>Reserve &rarr;</a>
                </div>
            </div>";
        }
        $html .= "</div>";

        if (count($availableRooms) > 5) {
            $html .= "<p style='font-size:10px; color:#94a3b8; margin-top:4px; margin-bottom:0; text-align:center;'>+ " . (count($availableRooms) - 5) . " more rooms available in catalog.</p>";
        }

        return [
            'text' => $html,
            'kind' => 'customer-availability',
            'quick_chips' => ['💰 Suite Rates', '🔍 My Booking', '🛎️ Concierge Desk'],
        ];
    }

    private function customerRoomTypeReply(): array
    {
        $typeSummary = $this->roomModel->typeSummary();
        $roomCatalog = roomCatalog();

        $html = "<div style='margin-bottom:6px; font-weight:bold; color:#ffdf73; font-size:13px;'>👑 Suite Categories:</div>";
        $html .= "<div style='display:flex; flex-direction:column; gap:6px;'>";

        foreach ($roomCatalog as $roomType => $roomInfo) {
            $summary = $typeSummary[$roomType] ?? null;
            $availableCount = $summary ? (int) $summary['available'] : 0;
            $totalCount = $summary ? (int) $summary['total'] : 0;
            $priceText = $summary ? formatMoney((float) $summary['lowest_price']) . '/night' : 'N/A';
            $suiteName = htmlspecialchars($roomType);

            $html .= "
            <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.35); border-radius: 8px; padding: 7px 10px;'>
                <div style='display:flex; justify-content:space-between; align-items:center;'>
                    <span style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:13px;'>{$suiteName}</span>
                    <span style='color:#94a3b8; font-size:11px;'>{$availableCount}/{$totalCount} available</span>
                </div>
                <div style='display:flex; justify-content:space-between; align-items:center; margin-top:4px;'>
                    <span style='font-size:11px; color:#e2e8f0;'>From <strong style='color:#ffdf73;'>{$priceText}</strong></span>
                    <a href='../site/suites.php' style='background:rgba(212,175,55,0.15); color:#ffdf73; border:1px solid rgba(212,175,55,0.4); font-weight:bold; padding:3px 10px; border-radius:6px; text-decoration:none; font-size:11px;'>Inspect &rarr;</a>
                </div>
            </div>";
        }
        $html .= "</div>";

        return [
            'text' => $html,
            'kind' => 'customer-room-types',
            'quick_chips' => ['📅 Available Rooms', '💰 Suite Rates', '🛎️ Concierge Desk'],
        ];
    }

    private function customerRoomPriceReply(): array
    {
        return $this->customerRoomTypeReply();
    }

    private function customerBookingReply(): array
    {
        $html = "
        <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.35); border-radius: 10px; padding: 10px 12px;'>
            <div style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:14px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;'>
                <span>🏨 How to Book a Room</span>
                <span style='font-size:10px; color:#94a3b8; background:rgba(212,175,55,0.12); padding:2px 6px; border-radius:99px;'>Booking Guide</span>
            </div>

            <div style='display:flex; flex-direction:column; gap:6px; margin-bottom:8px;'>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:6px 8px; border-radius:6px;'>
                    <strong style='color:#ffdf73; font-size:11px;'>1. Pick Dates & Select Room</strong>
                    <p style='font-size:11px; color:#cbd5e1; margin:2px 0 0 0;'>Select check-in & check-out dates, then browse live suite cards to choose your room.</p>
                </div>

                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:6px 8px; border-radius:6px;'>
                    <strong style='color:#ffdf73; font-size:11px;'>2. Select Payment Option</strong>
                    <p style='font-size:11px; color:#cbd5e1; margin:2px 0 0 0;'>Pay online instantly via Credit Card/E-Wallet or choose Cash to settle at front desk upon check-in.</p>
                </div>

                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:6px 8px; border-radius:6px;'>
                    <strong style='color:#ffdf73; font-size:11px;'>3. Instant Confirmation & Receipt</strong>
                    <p style='font-size:11px; color:#cbd5e1; margin:2px 0 0 0;'>Your reservation reference is generated immediately and visible on your guest dashboard.</p>
                </div>
            </div>

            <a href='../user/dashboard.php' style='display:block; text-align:center; background:linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color:#020617; font-weight:bold; padding:6px 10px; border-radius:6px; text-decoration:none; font-size:11px;'>Book a Suite Now &rarr;</a>
        </div>";

        return [
            'text' => $html,
            'kind' => 'customer-booking',
            'quick_chips' => ['📅 Available Rooms', '💰 Suite Rates', '🔍 My Booking'],
        ];
    }

    private function hotelProfileReply(): array
    {
        $foundedYear = $this->hotelProfile['founded_year'] ?? 2024;
        $yearsRunning = max(1, (int) date('Y') - (int) $foundedYear);
        $name = htmlspecialchars((string) $this->hotelProfile['name']);
        $desc = htmlspecialchars((string) $this->hotelProfile['description']);

        $html = "
        <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.35); border-radius: 10px; padding: 10px 12px;'>
            <div style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:14px; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;'>
                <span>👑 About {$name}</span>
                <span style='font-size:10px; color:#94a3b8;'>Est. {$foundedYear} ({$yearsRunning} yrs)</span>
            </div>
            <p style='font-size:11px; color:#cbd5e1; line-height:1.4; margin:0 0 8px 0;'>{$desc}</p>
            <div style='display:grid; grid-template-columns:1fr 1fr; gap:6px; font-size:11px;'>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:6px; border-radius:6px;'>
                    <span style='color:#94a3b8; font-size:10px; display:block;'>Support Email</span>
                    <strong style='color:#ffdf73;'>support@emperorhotel.com</strong>
                </div>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:6px; border-radius:6px;'>
                    <span style='color:#94a3b8; font-size:10px; display:block;'>Concierge Hotline</span>
                    <strong style='color:#4ade80;'>+63 2 8888 7777</strong>
                </div>
            </div>
            <a href='../site/about.php' style='display:block; text-align:center; background:linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color:#020617; font-weight:bold; padding:5px 10px; border-radius:6px; text-decoration:none; margin-top:8px; font-size:11px;'>Explore Hotel Story &rarr;</a>
        </div>";

        return [
            'text' => $html,
            'kind' => 'customer-hotel-profile',
            'quick_chips' => ['🏨 Room Categories', '💰 Suite Rates', '📅 Available Rooms'],
        ];
    }

    private function greetingReply(string $scope): array
    {
        $lines = $scope === 'admin'
            ? [
                'Hello. I can help with dashboard data, reservations, room stats, payments, and reports.',
                'Try asking about monthly sales, occupancy, available rooms, or a date range.',
            ]
            : [
                'Hello. I can help with available rooms, room prices, booking guidance, and hotel info.',
                'Try asking about available rooms, room types, or hotel history.',
            ];

        return $this->reply($lines, 'greeting');
    }

    private function datasetReply(array $match): array
    {
        return match ($match['route'] ?? 'faq') {
            'room-availability' => $this->customerAvailabilityReply(),
            'room-types' => $this->customerRoomTypeReply(),
            'room-prices' => $this->customerRoomPriceReply(),
            'hotel-history' => $this->hotelProfileReply(),
            'booking-guide' => $this->customerBookingReply(),
            default => $this->reply([(string) ($match['answer'] ?? 'How can I help you with Emperor Hotel today?')], 'dataset'),
        };
    }

    private function datasetMultiReply(array $matches): array
    {
        if (count($matches) === 1) {
            return $this->datasetReply($matches[0]);
        }

        $html = "<div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.35); border-radius: 10px; padding: 10px 12px;'>";
        $html .= "<div style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:14px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;'>";
        $html .= "<span>✨ Hotel Amenities & Services Details</span>";
        $html .= "<span style='font-size:10px; color:#94a3b8; background:rgba(212,175,55,0.12); padding:2px 6px; border-radius:99px;'>Concierge Guide</span>";
        $html .= "</div>";
        $html .= "<div style='display:flex; flex-direction:column; gap:6px;'>";

        $iconMap = [
            'breakfast' => '🍳',
            'buffet' => '🍳',
            'pool' => '🏊',
            'swimming' => '🏊',
            'spa' => '💆',
            'massage' => '💆',
            'gym' => '🏋️',
            'fitness' => '🏋️',
            'wifi' => '📶',
            'internet' => '📶',
            'parking' => '🅿️',
            'pet' => '🐾',
            'check in' => '🔑',
            'check out' => '🚪',
            'extra bed' => '🛏️',
            'cancellation' => '📋',
        ];

        foreach ($matches as $match) {
            $answer = htmlspecialchars((string) ($match['answer'] ?? ''));
            $title = htmlspecialchars(ucwords((string) ($match['patterns'][0] ?? 'Facility Details')));
            $icon = '✨';

            foreach ($iconMap as $key => $ic) {
                if (str_contains(strtolower($title), $key) || str_contains(strtolower($answer), $key)) {
                    $icon = $ic;
                    break;
                }
            }

            $html .= "
            <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:8px 10px; border-radius:6px;'>
                <strong style='color:#ffdf73; font-size:12px;'>{$icon} {$title}</strong>
                <p style='font-size:11px; color:#cbd5e1; margin:3px 0 0 0; line-height:1.4;'>{$answer}</p>
            </div>";
        }

        $html .= "</div></div>";

        return [
            'text' => $html,
            'kind' => 'customer-concierge',
            'quick_chips' => ['🏨 Room Categories', '💰 Suite Rates', '📅 Available Rooms'],
        ];
    }

    private function aiReply(string $scope, array $range, array $keywords): array
    {
        return [
            'kind' => 'ai',
            'reply' => $scope === 'admin'
                ? 'I can help with dashboard data, revenue, reservations, room status, and reports.'
                : 'I can help with available rooms, room prices, booking guidance, and hotel information.',
            'context' => $this->composeAiContext($scope, $range, $keywords),
        ];
    }

    private function adminOverviewReply(array $range): array
    {
        $dashboardSummary = $this->reservationModel->dashboardSummary();
        $roomSummary = $this->roomModel->statusSummary();
        $revenueThisMonth = $this->paymentModel->revenueThisMonth();
        $monthlyPerformance = $this->reservationModel->monthlyPerformance();

        $custCount = (int) $dashboardSummary['customers_this_month'];
        $pendingCount = (int) $dashboardSummary['pending_reservations'];
        $checkoutCount = (int) $dashboardSummary['upcoming_checkouts'];
        $availCount = (int) $roomSummary['available'];
        $unavailCount = (int) $roomSummary['not_available'];
        $formattedRev = formatMoney($revenueThisMonth);

        $html = "
        <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.35); border-radius: 10px; padding: 10px 12px;'>
            <div style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:14px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;'>
                <span>📊 Executive Admin Overview</span>
                <span style='font-size:11px; background:rgba(212,175,55,0.15); color:#ffdf73; padding:2px 8px; border-radius:99px; border:1px solid rgba(212,175,55,0.3);'>Live Sync</span>
            </div>

            <!-- KPI Tiles Grid -->
            <div style='display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:8px;'>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:6px 8px; border-radius:6px;'>
                    <span style='color:#94a3b8; font-size:10px; display:block;'>Monthly Revenue</span>
                    <strong style='color:#4ade80; font-size:13px;'>{$formattedRev}</strong>
                </div>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:6px 8px; border-radius:6px;'>
                    <span style='color:#94a3b8; font-size:10px; display:block;'>Active Guests</span>
                    <strong style='color:#ffdf73; font-size:13px;'>{$custCount} Guests</strong>
                </div>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:6px 8px; border-radius:6px;'>
                    <span style='color:#94a3b8; font-size:10px; display:block;'>Pending Action</span>
                    <strong style='color:#fbbf24; font-size:13px;'>{$pendingCount} Pending</strong>
                </div>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:6px 8px; border-radius:6px;'>
                    <span style='color:#94a3b8; font-size:10px; display:block;'>Room Vacancy</span>
                    <strong style='color:#38bdf8; font-size:13px;'>{$availCount} Avail / {$unavailCount} Booked</strong>
                </div>
            </div>";

        // Append Monthly Revenue Visual Graph
        $html .= $this->buildVisualSalesBarChart($monthlyPerformance);

        $html .= "<a href='../admin/dashboard.php' style='display:block; text-align:center; background:linear-gradient(135deg, #D4AF37, #FFDF73); color:#020617; font-weight:bold; padding:5px 10px; border-radius:6px; text-decoration:none; margin-top:8px; font-size:11px;'>Open Executive Dashboard &rarr;</a>";
        $html .= "</div>";

        return [
            'text' => $html,
            'kind' => 'admin-overview',
            'quick_chips' => ['📈 Monthly Sales', '📊 Executive Forecast', '🧹 Dirty Rooms', '🚨 Pending Risks'],
        ];
    }

    private function adminStatisticsReply(string $text, array $range): array
    {
        $dashboardSummary = $this->reservationModel->dashboardSummary();
        $roomSummary = $this->roomModel->statusSummary();
        $typeSummary = $this->roomModel->typeSummary();
        $monthlyPerformance = $this->reservationModel->monthlyPerformance();
        $revenueReport = $this->paymentModel->revenueReport($range['start'], $range['end']);
        $trendReport = $this->reservationModel->reservationTrendReport($range['start'], $range['end']);
        $occupancyReport = $this->reservationModel->occupancyReport($range['start'], $range['end']);

        $totalRevenue = formatMoney((float) $revenueReport['total_revenue']);
        $totalRes = (int) $trendReport['total_reservations'];
        $occPct = number_format((float) $occupancyReport['occupancy_rate'], 1);

        $html = "
        <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.35); border-radius: 10px; padding: 10px 12px;'>
            <div style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:14px; margin-bottom:8px;'>📈 Analytical Performance Statistics</div>

            <!-- Occupancy Visual Gauge Bar -->
            <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:8px; border-radius:8px; margin-bottom:8px;'>
                <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; font-size:11px;'>
                    <span style='color:#cbd5e1; font-weight:600;'>Occupancy Rate ({$range['start']} to {$range['end']})</span>
                    <strong style='color:#ffdf73; font-size:13px;'>{$occPct}%</strong>
                </div>
                <div style='width:100%; height:8px; background:rgba(255,255,255,0.08); border-radius:99px; overflow:hidden;'>
                    <div style='width:{$occPct}%; height:100%; background:linear-gradient(90deg, #38bdf8, #4ade80, #ffdf73); border-radius:99px;'></div>
                </div>
                <div style='display:flex; justify-content:space-between; font-size:10px; color:#94a3b8; margin-top:4px;'>
                    <span>Revenue: <strong style='color:#4ade80;'>{$totalRevenue}</strong></span>
                    <span>Bookings: <strong style='color:#ffdf73;'>{$totalRes}</strong></span>
                </div>
            </div>";

        // Room Type Inventory Visual Breakdown
        $html .= "<div style='margin-bottom:8px;'>";
        $html .= "<div style='color:#cbd5e1; font-weight:bold; font-size:11px; margin-bottom:4px;'>Room Inventory Availability:</div>";
        foreach ($typeSummary as $rType => $s) {
            $t = (int) $s['total'];
            $a = (int) $s['available'];
            $booked = max(0, $t - $a);
            $pct = $t > 0 ? (int) round(($booked / $t) * 100) : 0;
            $priceStr = formatMoney((float) $s['lowest_price']);

            $html .= "<div style='background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); padding:5px 8px; border-radius:6px; margin-bottom:4px;'>";
            $html .= "<div style='display:flex; justify-content:space-between; font-size:10px; margin-bottom:2px;'>";
            $html .= "<span style='color:#f8fafc; font-weight:600;'>{$rType} <small style='color:#94a3b8;'>({$priceStr}/nt)</small></span>";
            $html .= "<span style='color:#cbd5e1;'><strong>{$a}</strong> free / {$t} total ({$pct}% occ)</span>";
            $html .= "</div>";
            $html .= "<div style='width:100%; height:5px; background:rgba(255,255,255,0.08); border-radius:99px; overflow:hidden;'>";
            $html .= "<div style='width:{$pct}%; height:100%; background:linear-gradient(90deg, #4ade80, #ffdf73); border-radius:99px;'></div>";
            $html .= "</div>";
            $html .= "</div>";
        }
        $html .= "</div>";

        // Append Visual Bar Graph for Monthly Trend
        $html .= $this->buildVisualSalesBarChart($monthlyPerformance);

        $html .= "<a href='../admin/reports.php' style='display:block; text-align:center; background:linear-gradient(135deg, #D4AF37, #FFDF73); color:#020617; font-weight:bold; padding:5px 10px; border-radius:6px; text-decoration:none; margin-top:8px; font-size:11px;'>Detailed Analytics & Reports &rarr;</a>";
        $html .= "</div>";

        return [
            'text' => $html,
            'kind' => 'admin-statistics',
            'quick_chips' => ['📈 Monthly Sales', '📊 Executive Forecast', '🏆 Room Rankings', '🧹 Dirty Rooms'],
        ];
    }

    private function adminSalesReply(array $range): array
    {
        if (!$range) {
            $range = $this->defaultRange();
        }

        $report = $this->paymentModel->revenueReport($range['start'], $range['end']);
        $trend = $this->reservationModel->reservationTrendReport($range['start'], $range['end']);
        $occupancy = $this->reservationModel->occupancyReport($range['start'], $range['end']);
        $monthlyPerformance = $this->reservationModel->monthlyPerformance();

        $totRev = formatMoney((float) $report['total_revenue']);
        $totRes = (int) $trend['total_reservations'];
        $occPct = number_format((float) $occupancy['occupancy_rate'], 1);

        $html = "
        <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.35); border-radius: 10px; padding: 10px 12px;'>
            <div style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:14px; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;'>
                <span>💰 Sales & Revenue Report</span>
                <span style='font-size:10px; color:#94a3b8;'>{$range['start']} to {$range['end']}</span>
            </div>

            <!-- Summary Badges -->
            <div style='display:grid; grid-template-columns:1fr 1fr 1fr; gap:6px; margin-bottom:8px; text-align:center;'>
                <div style='background:rgba(74,222,128,0.1); border:1px solid rgba(74,222,128,0.3); padding:5px; border-radius:6px;'>
                    <span style='color:#94a3b8; font-size:9px; display:block;'>Total Revenue</span>
                    <strong style='color:#4ade80; font-size:12px;'>{$totRev}</strong>
                </div>
                <div style='background:rgba(255,223,115,0.1); border:1px solid rgba(255,223,115,0.3); padding:5px; border-radius:6px;'>
                    <span style='color:#94a3b8; font-size:9px; display:block;'>Reservations</span>
                    <strong style='color:#ffdf73; font-size:12px;'>{$totRes} Bookings</strong>
                </div>
                <div style='background:rgba(56,189,248,0.1); border:1px solid rgba(56,189,248,0.3); padding:5px; border-radius:6px;'>
                    <span style='color:#94a3b8; font-size:9px; display:block;'>Occupancy</span>
                    <strong style='color:#38bdf8; font-size:12px;'>{$occPct}%</strong>
                </div>
            </div>";

        // Visual Revenue Bars per Room Type
        $html .= $this->buildRoomTypeDistributionBars($report['by_room_type']);

        // Visual Monthly Sales Bar Graph
        $html .= $this->buildVisualSalesBarChart($monthlyPerformance);

        $html .= "<a href='../admin/reports.php' style='display:block; text-align:center; background:linear-gradient(135deg, #D4AF37, #FFDF73); color:#020617; font-weight:bold; padding:5px 10px; border-radius:6px; text-decoration:none; margin-top:8px; font-size:11px;'>Full Sales Reports &rarr;</a>";
        $html .= "</div>";

        return [
            'text' => $html,
            'kind' => 'admin-sales',
            'quick_chips' => ['📊 Executive Forecast', '🏆 Room Rankings', '🧹 Dirty Rooms', '🚨 Pending Risks'],
        ];
    }

    private function adminOperationsReply(array $range): array
    {
        $alerts = $this->reservationModel->operationalAlerts();
        $reportRange = $range ?? $this->defaultRange();
        $occupancy = $this->reservationModel->occupancyReport($reportRange['start'], $reportRange['end']);
        $trend = $this->reservationModel->reservationTrendReport($reportRange['start'], $reportRange['end']);

        $overdueCount = count($alerts['overdue_checkouts']);
        $conflictCount = count($alerts['overbooking_conflicts']);
        $totalRes = (int) $trend['total_reservations'];
        $occPct = number_format((float) $occupancy['occupancy_rate'], 1);

        $html = "
        <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.35); border-radius: 10px; padding: 10px 12px;'>
            <div style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:14px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;'>
                <span>⚙️ Front Desk Operations Watch</span>
                <span style='font-size:10px; color:#94a3b8;'>Realtime Alerts</span>
            </div>

            <!-- Risk Badges Grid -->
            <div style='display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:8px;'>
                <div style='background: " . ($overdueCount > 0 ? "rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.4);" : "rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3);") . " padding:6px 8px; border-radius:6px;'>
                    <span style='color:#94a3b8; font-size:10px; display:block;'>Overdue Check-Outs</span>
                    <strong style='color:" . ($overdueCount > 0 ? "#f87171" : "#4ade80") . "; font-size:13px;'>{$overdueCount} Overdue</strong>
                </div>
                <div style='background: " . ($conflictCount > 0 ? "rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.4);" : "rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3);") . " padding:6px 8px; border-radius:6px;'>
                    <span style='color:#94a3b8; font-size:10px; display:block;'>Booking Conflicts</span>
                    <strong style='color:" . ($conflictCount > 0 ? "#f87171" : "#4ade80") . "; font-size:13px;'>{$conflictCount} Conflicts</strong>
                </div>
            </div>";

        if ($alerts['overdue_checkouts']) {
            $html .= "<div style='font-size:11px; font-weight:bold; color:#f87171; margin-bottom:4px;'>⚠️ Overdue Guests List:</div>";
            $html .= "<div style='display:flex; flex-direction:column; gap:4px; margin-bottom:8px;'>";
            foreach ($alerts['overdue_checkouts'] as $row) {
                $num = htmlspecialchars((string) $row['room_number']);
                $gName = htmlspecialchars((string) ($row['first_name'] . ' ' . $row['last_name']));
                $outDate = htmlspecialchars((string) $row['check_out']);

                $html .= "<div style='background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); padding:4px 8px; border-radius:6px; display:flex; justify-content:space-between; font-size:11px;'>";
                $html .= "<span style='color:#f8fafc;'><strong>Room #{$num}</strong> &bull; {$gName}</span>";
                $html .= "<span style='color:#f87171; font-weight:bold;'>Due {$outDate}</span>";
                $html .= "</div>";
            }
            $html .= "</div>";
        } else {
            $html .= "<div style='background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); padding:6px; border-radius:6px; font-size:11px; color:#4ade80; text-align:center; margin-bottom:8px;'>";
            $html .= "✓ No overdue check-outs. All guest departures on schedule.";
            $html .= "</div>";
        }

        $html .= "<a href='../admin/reservations.php' style='display:block; text-align:center; background:linear-gradient(135deg, #D4AF37, #FFDF73); color:#020617; font-weight:bold; padding:5px 10px; border-radius:6px; text-decoration:none; margin-top:6px; font-size:11px;'>Manage Front Desk Reservations &rarr;</a>";
        $html .= "</div>";

        return [
            'text' => $html,
            'kind' => 'admin-operations',
            'quick_chips' => ['📈 Monthly Sales', '📊 Executive Forecast', '🧹 Dirty Rooms', '🏆 Room Rankings'],
        ];
    }

    private function buildVisualSalesBarChart(array $monthlyPerformance): string
    {
        $maxIncome = 1.0;
        foreach ($monthlyPerformance as $row) {
            $inc = (float) ($row['income'] ?? 0);
            if ($inc > $maxIncome) {
                $maxIncome = $inc;
            }
        }

        $html = "<div style='background:rgba(2,6,23,0.85); border:1px solid rgba(212,175,55,0.3); border-radius:8px; padding:8px 10px; margin-top:6px; margin-bottom:6px;'>";
        $html .= "<div style='color:#ffdf73; font-weight:bold; font-size:11px; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;'>";
        $html .= "<span>📈 Monthly Revenue Trend</span>";
        $html .= "<span style='color:#94a3b8; font-weight:normal; font-size:10px;'>6-Month Chart</span>";
        $html .= "</div>";

        $html .= "<div style='display:flex; align-items:flex-end; gap:6px; height:85px; padding-top:8px; border-bottom:1px solid rgba(255,255,255,0.1);'>";
        foreach ($monthlyPerformance as $row) {
            $inc = (float) ($row['income'] ?? 0);
            $heightPct = max(8, min(100, (int) round(($inc / $maxIncome) * 100)));
            $shortMonth = htmlspecialchars(substr((string) $row['month_label'], 0, 3));
            $formattedInc = '₱' . number_format($inc / 1000, 0) . 'k';

            $html .= "<div style='flex:1; display:flex; flex-direction:column; align-items:center; gap:2px; height:100%; justify-content:flex-end;'>";
            $html .= "<span style='font-size:9px; color:#ffdf73; font-weight:bold;'>{$formattedInc}</span>";
            $html .= "<div style='width:100%; max-width:22px; height:{$heightPct}%; background:linear-gradient(180deg, #FFDF73 0%, #D4AF37 60%, #996515 100%); border-radius:4px 4px 0 0;'></div>";
            $html .= "<span style='font-size:9px; color:#94a3b8; text-transform:uppercase;'>{$shortMonth}</span>";
            $html .= "</div>";
        }
        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function buildRoomTypeDistributionBars(array $byRoomType): string
    {
        $maxRev = 1.0;
        foreach ($byRoomType as $row) {
            $rev = (float) ($row['confirmed_revenue'] ?? 0);
            if ($rev > $maxRev) {
                $maxRev = $rev;
            }
        }

        $html = "<div style='margin-top:6px; margin-bottom:6px; display:flex; flex-direction:column; gap:4px;'>";
        $html .= "<div style='color:#cbd5e1; font-weight:bold; font-size:11px;'>Revenue Share by Suite Category:</div>";

        foreach ($byRoomType as $row) {
            $type = htmlspecialchars((string) $row['room_type']);
            $rev = (float) ($row['confirmed_revenue'] ?? 0);
            $cnt = (int) ($row['payment_count'] ?? 0);
            $pct = max(5, min(100, (int) round(($rev / $maxRev) * 100)));
            $formattedRev = formatMoney($rev);

            $html .= "<div style='background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); padding:5px 8px; border-radius:6px;'>";
            $html .= "<div style='display:flex; justify-content:space-between; font-size:10px; margin-bottom:2px;'>";
            $html .= "<span style='color:#f8fafc; font-weight:600;'>{$type} <small style='color:#94a3b8;'>({$cnt} stays)</small></span>";
            $html .= "<strong style='color:#4ade80;'>{$formattedRev}</strong>";
            $html .= "</div>";
            $html .= "<div style='width:100%; height:5px; background:rgba(255,255,255,0.08); border-radius:99px; overflow:hidden;'>";
            $html .= "<div style='width:{$pct}%; height:100%; background:linear-gradient(90deg, #D4AF37, #FFDF73); border-radius:99px;'></div>";
            $html .= "</div>";
            $html .= "</div>";
        }
        $html .= "</div>";

        return $html;
    }

    private function reply(array $lines, string $kind): array
    {
        return [
            'kind' => $kind,
            'reply' => implode(PHP_EOL, $lines),
        ];
    }

    private function normalizeScope(string $scope): string
    {
        return in_array($scope, ['admin', 'customer'], true) ? $scope : 'customer';
    }

    private function normalizeText(string $text): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? ''));
    }

    private function isGreeting(string $text): bool
    {
        $greetings = ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'];
        $pattern = '/\b(' . implode('|', array_map('preg_quote', $greetings)) . ')\b/i';
        if (!preg_match($pattern, $text)) {
            return false;
        }

        $words = explode(' ', $text);
        if (count($words) <= 3) {
            return true;
        }

        return false;
    }

    private function hasCustomerIntent(string $text): bool
    {
        return $this->matchesAny($text, ['room', 'suite', 'price', 'booking', 'reservation', 'hotel', 'history', 'founding', 'check in', 'check out', 'payment']);
    }

    private function hasAdminIntent(string $text): bool
    {
        return $this->matchesAny($text, ['dashboard', 'sales', 'revenue', 'income', 'graph', 'chart', 'occupancy', 'report', 'reservations', 'payments', 'alerts', 'room stats', 'statistics', 'stats', 'analytics', 'summary']);
    }

    private function isStatisticsIntent(string $text): bool
    {
        return $this->matchesAny($text, ['statistics', 'stats', 'analytics', 'summary', 'overview', 'numbers', 'metrics', 'performance', 'trend', 'how many', 'what is the revenue', 'what are the numbers']);
    }

    private function findAllDatasetMatches(string $normalizedMessage): array
    {
        $matches = [];
        $input = $normalizedMessage;

        foreach ($this->datasetEntries() as $entry) {
            foreach ($entry['patterns'] as $pattern) {
                $normalizedPattern = $this->normalizeText((string) $pattern);

                if ($normalizedPattern === '') {
                    continue;
                }

                if (!str_contains($input, $normalizedPattern)) {
                    continue;
                }

                if (strlen($normalizedPattern) < 4) {
                    $patternRegex = '/\b' . preg_quote($normalizedPattern, '/') . '\b/';
                    if (!preg_match($patternRegex, $input)) {
                        continue;
                    }
                }

                $alreadyAdded = false;
                foreach ($matches as $existing) {
                    if (($existing['answer'] ?? '') === ($entry['answer'] ?? '')) {
                        $alreadyAdded = true;
                        break;
                    }
                }

                if (!$alreadyAdded) {
                    $matches[] = $entry;
                }

                break;
            }
        }

        return $matches;
    }

    private function datasetEntries(): array
    {
        return [
            [
                'route' => 'faq',
                'patterns' => ['check in time', 'check-in time', 'what time can i check in', 'when is check in', 'arrival time', 'check in starts', 'when can i check in'],
                'answer' => 'Check-in starts at 3:00 PM. Early check-in may be available on request, subject to room availability.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['check out time', 'check-out time', 'what time is checkout', 'when do i need to check out', 'departure time', 'check out starts', 'when can i check out'],
                'answer' => 'Check-out is at 12:00 PM (noon). Late check-out may be arranged for an additional fee, subject to availability.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['wifi password', 'wi-fi password', 'internet access', 'how do i connect to wifi', 'wifi', 'internet password'],
                'answer' => 'Complimentary WiFi is available throughout the hotel. The network name and password are available at the front desk.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['breakfast hours', 'breakfast time', 'when is breakfast served', 'buffet hours', 'breakfast', 'breakfast served', 'morning buffet'],
                'answer' => 'Breakfast is served daily from 6:30 AM to 10:30 AM at our main restaurant.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['parking availability', 'do you have parking', 'valet parking', 'where can i park', 'parking', 'car parking', 'parking fee'],
                'answer' => 'On-site parking is available for guests. Valet parking may also be offered for an additional fee.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['pet policy', 'are pets allowed', 'can i bring my dog', 'can i bring my cat', 'pet friendly', 'pets allowed'],
                'answer' => 'Pets are welcome in designated rooms for an additional cleaning fee. Please let us know in advance.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['cancellation policy', 'how do i cancel my reservation', 'can i get a refund', 'cancel booking', 'refund policy'],
                'answer' => 'Reservations can usually be cancelled free of charge up to 24 hours before check-in.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['pool hours', 'swimming pool hours', 'when is the pool open', 'pool', 'pool open', 'pool schedule'],
                'answer' => 'Our swimming pool is open daily from 7:00 AM to 9:00 PM.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['gym hours', 'fitness center hours', 'when is the gym open', 'gym', 'workout room', 'exercise room'],
                'answer' => 'The fitness center is open 24/7 and accessible with your room key card.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['spa hours', 'spa booking', 'how do i book a massage', 'spa', 'massage booking', 'spa schedule'],
                'answer' => 'Our spa is open from 10:00 AM to 8:00 PM. We recommend booking treatments in advance.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['extra bed', 'rollaway bed', 'baby crib', 'do you have cribs', 'cribs', 'additional bed'],
                'answer' => 'Extra beds and cribs are available on request for an additional fee, subject to room capacity.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['payment methods', 'do you accept credit cards', 'can i pay cash', 'accepted payment', 'payment options', 'cash payment'],
                'answer' => 'We accept major credit cards, debit cards, and cash. Payment timing depends on booking type.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['room service hours', 'in room dining', 'order food to my room', 'room service', 'food delivery to room'],
                'answer' => 'Room service is available daily from 6:00 AM to 11:00 PM.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['airport shuttle', 'airport transfer', 'do you offer pickup', 'shuttle service', 'airport pickup', 'pickup service'],
                'answer' => 'We offer an airport shuttle service. Please contact the front desk at least 24 hours in advance.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['smoking policy', 'is smoking allowed', 'can i smoke in my room', 'smoking', 'smoke allowed'],
                'answer' => 'All guest rooms are non-smoking. Designated smoking areas are available outside the hotel entrance.',
            ],
            [
                'route' => 'faq',
                'patterns' => ['contact number', 'phone number', 'email address', 'contact support', 'support email', 'support phone', 'how to contact', 'contact details', 'phone', 'email'],
                'answer' => sprintf(
                    'You can contact our support team at %s or call us at %s.',
                    $this->hotelProfile['support_email'] ?: 'support@example.com',
                    $this->hotelProfile['support_phone'] ?: '(not provided)'
                ),
            ],
            [
                'route' => 'room-types',
                'patterns' => ['room types', 'what room types', 'types of rooms', 'room categories', 'room categories and prices', 'show room types'],
                'answer' => '',
            ],
            [
                'route' => 'room-availability',
                'patterns' => ['available rooms', 'room availability', 'rooms available', 'available room', 'show available rooms', 'which rooms are available'],
                'answer' => '',
            ],
            [
                'route' => 'room-prices',
                'patterns' => ['room prices', 'room price', 'room rates', 'price per night', 'pricing by room type', 'room type prices'],
                'answer' => '',
            ],
            [
                'route' => 'hotel-history',
                'patterns' => ['hotel history', 'about emperor hotel', 'tell me about emperor hotel', 'founded emperor hotel', 'founding of emperor hotel'],
                'answer' => '',
            ],
            [
                'route' => 'booking-guide',
                'patterns' => ['how to book', 'booking guide', 'how to make a reservation', 'booking instructions', 'reservation guide', 'how do i book', 'book a room', 'make a booking'],
                'answer' => '',
            ],
        ];
    }

    private function composeAiContext(string $scope, array $range, array $keywords): string
    {
        $lines = [];

        // Add current date context
        $todayStr = (new DateTimeImmutable('today'))->format('Y-m-d');
        $lines[] = 'Current date: ' . $todayStr;

        if ($scope === 'admin') {
            $lines[] = 'Admin support context:';
            $lines[] = '- Current dashboard summary is available from live hotel data.';
            $lines[] = '- If a date range is present, use report data for that range.';
            $lines[] = '- If the user asks for charts, summarize revenue, occupancy, reservations, and room status.';
            $lines[] = '- If the user asks about room availability, room types, or room pricing, use a markdown table in its own paragraph block.';

            // Get live dashboard and room stats
            $dashboardSummary = $this->reservationModel->dashboardSummary();
            $roomSummary = $this->roomModel->statusSummary();
            $typeSummary = $this->roomModel->typeSummary();
            $revenueThisMonth = $this->paymentModel->revenueThisMonth();
            $monthlyPerformance = $this->reservationModel->monthlyPerformance();

            $lines[] = 'Live Admin Data:';
            $lines[] = sprintf('- Customers this month: %d', (int) $dashboardSummary['customers_this_month']);
            $lines[] = sprintf('- Pending reservations: %d', (int) $dashboardSummary['pending_reservations']);
            $lines[] = sprintf('- Upcoming check-outs: %d', (int) $dashboardSummary['upcoming_checkouts']);
            $lines[] = sprintf('- Available rooms: %d', (int) $roomSummary['available']);
            $lines[] = sprintf('- Rooms not available: %d', (int) $roomSummary['not_available']);
            $lines[] = sprintf('- Confirmed revenue this month: %s', formatMoney($revenueThisMonth));
            
            $lines[] = 'Monthly Performance:';
            foreach ($monthlyPerformance as $row) {
                $lines[] = sprintf(
                    '  * Month: %s | Bookings: %d | Income: %s',
                    $row['month_label'],
                    (int) $row['rooms_booked'],
                    formatMoney((float) $row['income'])
                );
            }

            // Room inventory details
            $lines[] = 'Room Inventory status:';
            foreach ($typeSummary as $roomType => $summary) {
                $lines[] = sprintf(
                    '  * Type: %s | Total: %d | Available: %d | Lowest price: %s',
                    $roomType,
                    (int) $summary['total'],
                    (int) $summary['available'],
                    formatMoney((float) $summary['lowest_price'])
                );
            }

            // Specific Room Number Performance Data
            $roomStats = $this->reservationModel->roomPerformanceStats();
            $lines[] = 'Specific Room Numbers Performance Rankings (All Rooms):';
            foreach ($roomStats as $row) {
                $lines[] = sprintf(
                    '  * Room #%s (Type: %s, Floor: %d, Price: %s): %d bookings, %d nights, total revenue: %s',
                    $row['room_number'],
                    $row['room_type'],
                    (int) $row['floor'],
                    formatMoney((float) $row['price_per_night']),
                    (int) $row['total_bookings'],
                    (int) $row['total_nights_booked'],
                    formatMoney((float) $row['total_revenue'])
                );
            }

            // Get operational alerts
            $alerts = $this->reservationModel->operationalAlerts();
            $lines[] = 'Operational Alerts:';
            $lines[] = sprintf('  * Overdue check-outs count: %d', count($alerts['overdue_checkouts']));
            $lines[] = sprintf('  * Overbooking conflicts count: %d', count($alerts['overbooking_conflicts']));
            foreach ($alerts['overdue_checkouts'] as $row) {
                $lines[] = sprintf(
                    '  * Overdue check-out alert: Room %s | Guest %s %s | Due %s',
                    $row['room_number'],
                    $row['first_name'],
                    $row['last_name'],
                    $row['check_out']
                );
            }

            // If a range is present, include the range details
            if ($range) {
                $report = $this->paymentModel->revenueReport($range['start'], $range['end']);
                $trend = $this->reservationModel->reservationTrendReport($range['start'], $range['end']);
                $occupancy = $this->reservationModel->occupancyReport($range['start'], $range['end']);

                $lines[] = sprintf('Range Data (%s to %s):', $range['start'], $range['end']);
                $lines[] = sprintf('  * Revenue: %s', formatMoney((float) $report['total_revenue']));
                $lines[] = sprintf('  * Reservations created: %d', (int) $trend['total_reservations']);
                $lines[] = sprintf('  * Occupancy rate: %s', number_format((float) $occupancy['occupancy_rate'], 1) . '%');
                $lines[] = '  * Revenue by room type in range:';
                foreach ($report['by_room_type'] as $row) {
                    $lines[] = sprintf(
                        '    - %s: %s (Count: %d)',
                        $row['room_type'],
                        formatMoney((float) $row['confirmed_revenue']),
                        (int) $row['payment_count']
                    );
                }
            }
        } else {
            $lines[] = 'Customer support context:';
            $lines[] = '- Available rooms, room types, prices, and hotel info are available from live hotel data.';
            $lines[] = '- Stay within customer-facing answers unless the user explicitly asks for admin information.';
            $lines[] = '- If the user asks about rooms, types, or pricing, use a markdown table in its own paragraph block.';

            // Get live customer-facing room & hotel data
            $availableRooms = $this->roomModel->availableRooms();
            $typeSummary = $this->roomModel->typeSummary();
            $roomCatalog = roomCatalog();

            $lines[] = 'Hotel Profile:';
            $lines[] = '- Name: ' . $this->hotelProfile['name'];
            $lines[] = '- Description: ' . $this->hotelProfile['description'];
            $lines[] = '- Founded: ' . ($this->hotelProfile['founded_year'] ?: $this->hotelProfile['founded_note']);
            $lines[] = '- Support Email: ' . ($this->hotelProfile['support_email'] ?: 'support@example.com');
            $lines[] = '- Support Phone: ' . ($this->hotelProfile['support_phone'] ?: 'not provided');

            $lines[] = 'Available Rooms:';
            if (!$availableRooms) {
                $lines[] = '  (None available right now)';
            } else {
                foreach ($availableRooms as $room) {
                    $lines[] = sprintf(
                        '  * Room %s | Type: %s | Floor: %s | Price: %s',
                        $room['room_number'],
                        $room['room_type'],
                        $room['floor'],
                        formatMoney((float) $room['price_per_night'])
                    );
                }
            }

            $lines[] = 'Room Catalog & Pricing:';
            foreach ($roomCatalog as $roomType => $info) {
                $summary = $typeSummary[$roomType] ?? null;
                $lowestPrice = $summary ? (float) $summary['lowest_price'] : 0.0;
                $availableCount = $summary ? (int) $summary['available'] : 0;
                $totalCount = $summary ? (int) $summary['total'] : 0;

                $lines[] = sprintf(
                    '  * Type: %s | Lowest Price: %s | Availability: %d of %d available',
                    $roomType,
                    $lowestPrice > 0 ? formatMoney($lowestPrice) : 'not set',
                    $availableCount,
                    $totalCount
                );
                $lines[] = '    - Perks: ' . implode(', ', $info['included_perks']);
                $lines[] = '    - Features: ' . implode(', ', $info['features']);
                $lines[] = '    - Details: ' . $info['details'];
            }
        }

        if ($range) {
            $lines[] = '- Report range: ' . $range['start'] . ' to ' . $range['end'];
        }

        if ($keywords) {
            $lines[] = '- Keywords: ' . implode(', ', array_slice(array_values(array_unique($keywords)), 0, 8));
        }

        return implode(PHP_EOL, $lines);
    }

    private function buildContextText(string $message, array $history, array $keywords): string
    {
        $parts = [$message];

        foreach (array_slice($history, -10) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $parts[] = (string) ($entry['text'] ?? '');
        }

        foreach ($keywords as $keyword) {
            $parts[] = (string) $keyword;
        }

        return $this->normalizeText(implode(' ', $parts));
    }

    private function buildMessageContext(string $message, array $keywords = []): string
    {
        $parts = [$message];

        foreach ($keywords as $keyword) {
            $parts[] = (string) $keyword;
        }

        return $this->normalizeText(implode(' ', $parts));
    }

    private function matchesAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function extractDateRange(string $text): ?array
    {
        $today = new DateTimeImmutable('today');
        $currentYear = (int) $today->format('Y');

        if (str_contains($text, 'this month') || str_contains($text, 'current month')) {
            return [
                'start' => $today->modify('first day of this month')->format('Y-m-d'),
                'end' => $today->format('Y-m-d'),
            ];
        }

        if (str_contains($text, 'last month')) {
            $start = $today->modify('first day of last month');
            $end = $today->modify('last day of last month');

            return [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ];
        }

        if (str_contains($text, 'yesterday')) {
            $date = $today->modify('-1 day')->format('Y-m-d');
            return [
                'start' => $date,
                'end' => $date,
            ];
        }

        if (str_contains($text, 'today')) {
            $date = $today->format('Y-m-d');

            return [
                'start' => $date,
                'end' => $date,
            ];
        }

        if (str_contains($text, 'last 7 days')) {
            return [
                'start' => $today->modify('-6 days')->format('Y-m-d'),
                'end' => $today->format('Y-m-d'),
            ];
        }

        if (str_contains($text, 'last 30 days')) {
            return [
                'start' => $today->modify('-29 days')->format('Y-m-d'),
                'end' => $today->format('Y-m-d'),
            ];
        }

        if (str_contains($text, 'last 90 days')) {
            return [
                'start' => $today->modify('-89 days')->format('Y-m-d'),
                'end' => $today->format('Y-m-d'),
            ];
        }

        if (preg_match('/from\s+(\d{4}-\d{2}-\d{2})\s+(?:to|until|through|-)\s+(\d{4}-\d{2}-\d{2})/', $text, $matches)) {
            return [
                'start' => $matches[1],
                'end' => $matches[2],
            ];
        }

        if (preg_match('/on\s+(\d{4}-\d{2}-\d{2})/', $text, $matches)) {
            return [
                'start' => $matches[1],
                'end' => $matches[1],
            ];
        }

        $months = [
            'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6,
            'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6,
            'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12
        ];

        foreach ($months as $monthName => $monthNum) {
            if (preg_match('/\b' . preg_quote($monthName, '/') . '\b/', $text)) {
                $targetYear = $currentYear;
                $currentMonthNum = (int) $today->format('m');
                if ($monthNum > $currentMonthNum) {
                    $targetYear = $currentYear - 1;
                }

                $startDate = sprintf('%04d-%02d-01', $targetYear, $monthNum);
                $dateTime = new DateTimeImmutable($startDate);
                $endDate = $dateTime->modify('last day of this month')->format('Y-m-d');

                return [
                    'start' => $startDate,
                    'end' => $endDate,
                ];
            }
        }

        return null;
    }

    private function adminExecutiveForecastReply(): array
    {
        $analytics = $this->reservationModel->advancedHospitalityAnalytics();
        $alos = number_format((float) ($analytics['alos_nights'] ?? 0), 1);
        $leadTime = number_format((float) ($analytics['avg_lead_time_days'] ?? 0), 1);
        $cancellationRate = number_format((float) ($analytics['cancellation_loss_rate'] ?? 0), 1);
        $loyaltyRatio = number_format((float) ($analytics['repeat_guest_loyalty_ratio'] ?? 0), 1);

        $html = "
        <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.35); border-radius: 8px; padding: 8px 10px;'>
            <div style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:13px; margin-bottom:6px;'>📊 Executive Metrics</div>
            <div style='display:grid; grid-template-columns:1fr 1fr; gap:6px; font-size:11px;'>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:6px; border-radius:6px;'>
                    <span style='color:#94a3b8; display:block;'>Avg Stay (ALOS)</span>
                    <strong style='color:#ffdf73; font-size:13px;'>{$alos} Nights</strong>
                </div>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:6px; border-radius:6px;'>
                    <span style='color:#94a3b8; display:block;'>Lead Time</span>
                    <strong style='color:#ffdf73; font-size:13px;'>{$leadTime} Days</strong>
                </div>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:6px; border-radius:6px;'>
                    <span style='color:#94a3b8; display:block;'>Cancellation</span>
                    <strong style='color:#f87171; font-size:13px;'>{$cancellationRate}%</strong>
                </div>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:6px; border-radius:6px;'>
                    <span style='color:#94a3b8; display:block;'>Repeat Guest</span>
                    <strong style='color:#4ade80; font-size:13px;'>{$loyaltyRatio}%</strong>
                </div>
            </div>
            <a href='../admin/reports.php' style='display:block; text-align:center; background:linear-gradient(135deg, #D4AF37, #FFDF73); color:#020617; font-weight:bold; padding:5px 10px; border-radius:6px; text-decoration:none; margin-top:8px; font-size:11px;'>Full Reports &rarr;</a>
        </div>";

        return [
            'text' => $html,
            'kind' => 'admin-forecast',
            'quick_chips' => ['📈 Monthly Sales', '🧹 Dirty Rooms', '🚨 Pending Risks', '📊 Room Occupancy'],
        ];
    }

    private function adminMostBookedRoomReply(array $range = []): array
    {
        $startDate = $range['start'] ?? null;
        $endDate = $range['end'] ?? null;
        $stats = $this->reservationModel->roomPerformanceStats($startDate, $endDate);

        if (!$stats) {
            $html = "<div style='background: rgba(15,23,42,0.9); border: 1px solid rgba(212,175,55,0.4); border-radius: 8px; padding: 10px; text-align: center;'>
                        <strong style='color:#ffdf73; font-size:12px;'>No Room Performance Data Available</strong>
                     </div>";
            return [
                'text' => $html,
                'kind' => 'admin-room-rankings',
                'quick_chips' => ['📊 Executive Forecast', '📈 Monthly Sales', '🧹 Dirty Rooms'],
            ];
        }

        $topRoom = $stats[0];
        $topNum = htmlspecialchars((string) $topRoom['room_number']);
        $topType = htmlspecialchars((string) $topRoom['room_type']);
        $topBookings = (int) $topRoom['total_bookings'];
        $topNights = (int) $topRoom['total_nights_booked'];
        $topRev = formatMoney((float) $topRoom['total_revenue']);

        $html = "<div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.35); border-radius: 8px; padding: 8px 10px;'>"
              . "<div style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:13px; margin-bottom:6px;'>🏆 Most Booked Room Number</div>"
              . "<div style='background:rgba(212,175,55,0.12); border:1px solid rgba(212,175,55,0.4); border-radius:6px; padding:6px 8px; margin-bottom:6px; text-align:center;'>"
              . "<strong style='color:#ffdf73; font-size:13px; display:block;'>Room #{$topNum} &bull; {$topType}</strong>"
              . "<div style='font-size:11px; color:#cbd5e1; margin-top:3px;'><strong>{$topBookings} Bookings</strong> ({$topNights} Nights) &bull; Revenue: <strong style='color:#4ade80; white-space:nowrap;'>{$topRev}</strong></div>"
              . "</div>"
              . "<div style='font-size:11px; color:#94a3b8; font-weight:bold; margin-bottom:4px;'>Top Performing Rooms Ranking:</div>"
              . "<div style='display:flex; flex-direction:column; gap:4px;'>";

        foreach (array_slice($stats, 1, 4) as $index => $room) {
            $num = htmlspecialchars((string) $room['room_number']);
            $type = htmlspecialchars((string) $room['room_type']);
            $bookings = (int) $room['total_bookings'];
            $rev = formatMoney((float) $room['total_revenue']);
            $rank = $index + 2;

            $html .= "<div style='display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); padding:4px 8px; border-radius:6px; font-size:11px;'>"
                  . "<span style='color:#cbd5e1;'>#{$rank}. <strong style='color:#ffdf73;'>Room #{$num}</strong> ({$type})</span>"
                  . "<span style='color:#94a3b8; white-space:nowrap;'>{$bookings} stays &bull; {$rev}</span>"
                  . "</div>";
        }

        $html .= "</div>"
              . "<a href='../admin/reports.php' style='display:block; text-align:center; background:linear-gradient(135deg, #D4AF37, #FFDF73); color:#020617; font-weight:bold; padding:4px 10px; border-radius:6px; text-decoration:none; margin-top:6px; font-size:11px;'>Full Analytics &rarr;</a>"
              . "</div>";

        return [
            'text' => $html,
            'kind' => 'admin-room-rankings',
            'quick_chips' => ['📊 Executive Forecast', '📈 Monthly Sales', '🧹 Dirty Rooms'],
        ];
    }

    private function adminHousekeepingReply(): array
    {
        $sql = "SELECT room_id, room_number, room_type, floor, status FROM rooms WHERE status IN ('Cleaning', 'Maintenance') ORDER BY floor ASC, room_number ASC";
        $stmt = $this->db->query($sql);
        $dirtyRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$dirtyRooms) {
            $html = "<div style='background: rgba(15,23,42,0.9); border: 1px solid rgba(34,197,94,0.4); border-radius: 8px; padding: 10px; text-align: center;'>
                        <strong style='color:#4ade80; font-size:12px;'>All Rooms Clean & Ready!</strong>
                        <p style='font-size:11px; color:#94a3b8; margin: 3px 0 0 0;'>Zero rooms currently require housekeeping or maintenance.</p>
                     </div>";
            return [
                'text' => $html,
                'kind' => 'admin-housekeeping',
                'quick_chips' => ['📊 Executive Forecast', '📈 Monthly Sales', '🏨 Room Occupancy'],
            ];
        }

        $html = "<div style='margin-bottom:6px; font-weight:bold; color:#ffdf73; font-size:13px;'>🧹 Housekeeping Watchlist (" . count($dirtyRooms) . " Rooms):</div>";
        $html .= "<div style='display:flex; flex-direction:column; gap:6px;'>";

        foreach ($dirtyRooms as $room) {
            $roomNum = htmlspecialchars((string) $room['room_number']);
            $suiteType = htmlspecialchars((string) $room['room_type']);
            $status = htmlspecialchars((string) $room['status']);
            $floor = (int) $room['floor'];
            $badgeColor = $status === 'Cleaning' ? 'background:rgba(234,179,8,0.2); color:#eab308; border:1px solid rgba(234,179,8,0.4);' : 'background:rgba(239,68,68,0.2); color:#ef4444; border:1px solid rgba(239,68,68,0.4);';

            $html .= "
            <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.3); border-radius: 8px; padding: 6px 10px; display:flex; justify-content:space-between; align-items:center;'>
                <div>
                    <strong style='color:#ffdf73; font-size:12px;'>Room #{$roomNum}</strong>
                    <span style='font-size:10px; color:#94a3b8;'> &bull; Fl {$floor} &bull; {$suiteType}</span>
                </div>
                <span style='{$badgeColor} padding:1px 6px; border-radius:99px; font-size:10px; font-weight:bold;'>{$status}</span>
            </div>";
        }
        $html .= "</div>";
        $html .= "<a href='../admin/rooms.php' style='display:block; text-align:center; background:rgba(212,175,55,0.15); color:#ffdf73; border:1px solid rgba(212,175,55,0.4); font-weight:bold; padding:4px 10px; border-radius:6px; text-decoration:none; margin-top:6px; font-size:11px;'>Manage Status &rarr;</a>";

        return [
            'text' => $html,
            'kind' => 'admin-housekeeping',
            'quick_chips' => ['📊 Executive Forecast', '📈 Monthly Sales', '🚨 Pending Risks'],
        ];
    }

    private function customerConciergeServicesReply(): array
    {
        $html = "
        <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.35); border-radius: 8px; padding: 10px;'>
            <div style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:13px; margin-bottom:6px;'>🛎️ Concierge Services</div>
            <div style='font-size:11px; color:#cbd5e1; display:flex; flex-direction:column; gap:4px;'>
                <div>🏊 <strong>Rooftop Infinity Pool</strong>: 6:00 AM – 10:00 PM</div>
                <div>💆 <strong>Emperor Royal Spa</strong>: 8:00 AM – 11:00 PM</div>
                <div>🍳 <strong>Skyline Breakfast Buffet</strong>: 6:30 AM – 10:30 AM</div>
                <div>🚗 <strong>Airport Chauffeur Transfer</strong>: 24/7 Service</div>
            </div>
            <a href='../site/contact.php' style='display:block; text-align:center; background:linear-gradient(135deg, #D4AF37, #FFDF73); color:#020617; font-weight:bold; padding:5px 10px; border-radius:6px; text-decoration:none; margin-top:8px; font-size:11px;'>Contact Concierge &rarr;</a>
        </div>";

        return [
            'text' => $html,
            'kind' => 'customer-concierge',
            'quick_chips' => ['📅 Available Rooms', '💰 Suite Rates', '🔍 Check My Booking'],
        ];
    }

    private function customerReservationLookupReply(): array
    {
        $user = currentUser();

        if (!$user) {
            $html = "<div style='background: rgba(15,23,42,0.9); border: 1px solid rgba(212,175,55,0.4); border-radius: 8px; padding: 10px; text-align: center;'>
                        <strong style='color:#ffdf73; font-size:12px;'>Please Log In</strong>
                        <p style='font-size:11px; color:#94a3b8; margin: 3px 0 8px 0;'>Log in to view active bookings and receipts.</p>
                        <a href='../auth/login.php' style='display:inline-block; background:linear-gradient(135deg, #D4AF37, #FFDF73); color:#020617; font-weight:bold; padding:4px 12px; border-radius:6px; text-decoration:none; font-size:11px;'>Log In Now &rarr;</a>
                     </div>";
            return [
                'text' => $html,
                'kind' => 'customer-lookup',
                'quick_chips' => ['📅 Available Rooms', '💰 Suite Rates', '🛎️ Concierge Desk'],
            ];
        }

        $userId = (int) $user['user_id'];
        $sql = "SELECT r.*, rm.room_number, rm.room_type 
                FROM reservations r 
                JOIN rooms rm ON r.room_id = rm.room_id 
                WHERE r.user_id = :user_id 
                ORDER BY r.created_at DESC LIMIT 3";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$reservations) {
            $html = "<div style='background: rgba(15,23,42,0.9); border: 1px solid rgba(212,175,55,0.4); border-radius: 8px; padding: 10px; text-align: center;'>
                        <strong style='color:#ffdf73; font-size:12px;'>No Active Reservations Found</strong>
                        <p style='font-size:11px; color:#94a3b8; margin: 3px 0 8px 0;'>Pick a room to start your booking!</p>
                        <a href='../user/dashboard.php' style='display:inline-block; background:linear-gradient(135deg, #D4AF37, #FFDF73); color:#020617; font-weight:bold; padding:4px 12px; border-radius:6px; text-decoration:none; font-size:11px;'>Book a Suite &rarr;</a>
                     </div>";
            return [
                'text' => $html,
                'kind' => 'customer-lookup',
                'quick_chips' => ['📅 Available Rooms', '💰 Suite Rates', '🛎️ Concierge Desk'],
            ];
        }

        $html = "<div style='margin-bottom:6px; font-weight:bold; color:#ffdf73; font-size:13px;'>📋 Your Reservations:</div>";
        $html .= "<div style='display:flex; flex-direction:column; gap:6px;'>";

        foreach ($reservations as $res) {
            $resId = (int) $res['reservation_id'];
            $roomNum = htmlspecialchars((string) $res['room_number']);
            $suiteType = htmlspecialchars((string) $res['room_type']);
            $status = htmlspecialchars((string) $res['status']);
            $totalFormatted = formatMoney((float) $res['total_amount']);

            $badgeColor = match ($status) {
                'Confirmed', 'Checked-in' => 'background:rgba(34,197,94,0.2); color:#4ade80; border:1px solid rgba(34,197,94,0.4);',
                'Pending' => 'background:rgba(234,179,8,0.2); color:#eab308; border:1px solid rgba(234,179,8,0.4);',
                default => 'background:rgba(148,163,184,0.2); color:#cbd5e1; border:1px solid rgba(148,163,184,0.4);',
            };

            $html .= "
            <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.35); border-radius: 8px; padding: 8px 10px;'>
                <div style='display:flex; justify-content:space-between; align-items:center;'>
                    <span style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:12px;'>#{$resId} &bull; Room #{$roomNum}</span>
                    <span style='{$badgeColor} padding:1px 6px; border-radius:99px; font-size:10px; font-weight:bold;'>{$status}</span>
                </div>
                <div style='display:flex; justify-content:space-between; align-items:center; margin-top:4px;'>
                    <span style='font-size:11px; color:#cbd5e1;'>{$suiteType} &bull; <strong style='color:#ffdf73;'>{$totalFormatted}</strong></span>
                    <a href='../user/receipt.php?reservation_id={$resId}' target='_blank' style='background:rgba(212,175,55,0.15); color:#ffdf73; border:1px solid rgba(212,175,55,0.4); font-weight:bold; padding:2px 8px; border-radius:6px; text-decoration:none; font-size:10px;'>📄 Receipt &rarr;</a>
                </div>
            </div>";
        }
        $html .= "</div>";

        return [
            'text' => $html,
            'kind' => 'customer-lookup',
            'quick_chips' => ['📅 Available Rooms', '💰 Suite Rates', '🛎️ Concierge Desk'],
        ];
    }

    private function defaultRange(): array
    {
        $today = new DateTimeImmutable('today');

        return [
            'start' => $today->modify('first day of this month')->format('Y-m-d'),
            'end' => $today->format('Y-m-d'),
        ];
    }
}
