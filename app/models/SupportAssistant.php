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
        $datasetMatch = $this->findBestDatasetMatch($normalized);

        if ($isGreeting) {
            return $this->greetingReply($scope);
        }

        if ($datasetMatch !== null) {
            return $this->datasetReply($datasetMatch);
        }

        if ($scope === 'admin') {
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

        if ($this->matchesAny($normalized, ['spa', 'dining', 'pool', 'breakfast', 'towel', 'pillows', 'shuttle', 'amenities', 'menu'])) {
            return $this->customerConciergeServicesReply();
        }

        if ($this->matchesAny($normalized, ['my booking', 'reservation status', 'my receipt', 'booking status', 'lookup reservation'])) {
            return $this->customerReservationLookupReply();
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
            $html = "<div style='background: rgba(15,23,42,0.9); border: 1px solid rgba(212,175,55,0.4); border-radius: 12px; padding: 16px; text-align: center;'>
                        <strong style='color:#ffdf73;'>All Rooms Fully Booked</strong>
                        <p style='font-size:12px; color:#94a3b8; margin: 6px 0 0 0;'>There are currently no vacant rooms available for the selected dates. Please try different stay dates.</p>
                     </div>";
            return [
                'text' => $html,
                'kind' => 'customer-availability',
                'quick_chips' => ['💰 Suite Rates', '🏨 Room Categories', '🛎️ Concierge Desk'],
            ];
        }

        $html = "<div style='margin-bottom:10px; font-weight:bold; color:#ffdf73;'>🏨 Available Luxury Rooms (" . count($availableRooms) . " Ready):</div>";
        $html .= "<div style='display:flex; flex-direction:column; gap:10px;'>";

        foreach (array_slice($availableRooms, 0, 5) as $room) {
            $priceFormatted = formatMoney((float) $room['price_per_night']);
            $roomId = (int) $room['room_id'];
            $roomNum = htmlspecialchars((string) $room['room_number']);
            $suiteType = htmlspecialchars((string) $room['room_type']);
            $floor = (int) $room['floor'];

            $html .= "
            <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.4); border-radius: 12px; padding: 12px 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.3);'>
                <div style='display:flex; justify-content:space-between; align-items:center;'>
                    <span style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:14px;'>Room #{$roomNum} &bull; {$suiteType}</span>
                    <span style='background:rgba(34,197,94,0.2); color:#4ade80; border:1px solid rgba(34,197,94,0.4); padding:2px 8px; border-radius:99px; font-size:11px; font-weight:bold;'>Available</span>
                </div>
                <div style='font-size:12px; color:#94a3b8; margin: 6px 0;'>Floor {$floor} &bull; <strong style='color:#ffdf73;'>{$priceFormatted}</strong> / night</div>
                <a href='../user/dashboard.php?room_id={$roomId}' style='display:block; text-align:center; background:linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color:#020617; font-weight:bold; padding:7px 12px; border-radius:8px; text-decoration:none; margin-top:8px; font-size:12px;'>Reserve Room #{$roomNum} Now &rarr;</a>
            </div>";
        }
        $html .= "</div>";

        if (count($availableRooms) > 5) {
            $html .= "<p style='font-size:11px; color:#94a3b8; margin-top:8px; text-align:center;'>+ " . (count($availableRooms) - 5) . " more rooms available in catalog.</p>";
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
        
        $html = "<div style='margin-bottom:10px; font-weight:bold; color:#ffdf73;'>👑 Emperor Hotel Suite Categories:</div>";
        $html .= "<div style='display:flex; flex-direction:column; gap:10px;'>";

        foreach ($roomCatalog as $roomType => $roomInfo) {
            $summary = $typeSummary[$roomType] ?? null;
            $availableCount = $summary ? (int) $summary['available'] : 0;
            $totalCount = $summary ? (int) $summary['total'] : 0;
            $priceText = $summary ? formatMoney((float) $summary['lowest_price']) . ' / night' : 'Price not set';
            $perks = htmlspecialchars(implode(' &bull; ', array_slice($roomInfo['included_perks'], 0, 3)));
            $suiteName = htmlspecialchars($roomType);

            $html .= "
            <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.4); border-radius: 12px; padding: 12px 14px;'>
                <div style='display:flex; justify-content:space-between; align-items:center;'>
                    <span style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:14px;'>{$suiteName}</span>
                    <span style='color:#94a3b8; font-size:12px;'>{$availableCount} of {$totalCount} available</span>
                </div>
                <div style='font-size:12px; color:#e2e8f0; margin: 4px 0;'><strong style='color:#ffdf73;'>From {$priceText}</strong></div>
                <div style='font-size:11px; color:#94a3b8;'>Perks: {$perks}</div>
                <a href='../site/suites.php' style='display:block; text-align:center; background:rgba(212,175,55,0.15); color:#ffdf73; border:1px solid rgba(212,175,55,0.4); font-weight:bold; padding:6px 12px; border-radius:8px; text-decoration:none; margin-top:8px; font-size:12px;'>Inspect {$suiteName} Gallery &rarr;</a>
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
        $lines = [
            'Here is the step-by-step guide to make a room booking at Emperor Hotel:',
            '',
            '### Guest Booking Guide:',
            '1. **Log In**: Access your guest account (or sign up if you don\'t have one).',
            '2. **Go to User Dashboard**: Navigate to the booking area on your dashboard.',
            '3. **Stay Dates**: Select your check-in and check-out dates in the stay details form.',
            '4. **Select Room**: Browse the live room cards on the right-hand panel and pick your preferred room.',
            '5. **Choose Payment Mode**: Select "Cash" to pay at the front desk (creates a pending payment reference) or choose card/online transfer.',
            '6. **Confirm Booking**: Submit the form. If you chose card/online payment, complete the simulated payment screen.',
            '7. **Track Status**: Scroll to the "Booking History" table at the bottom of your dashboard to view your booking details and status (Pending, Confirmed, etc.).',
            '',
            '### Staff/Admin Walk-in Booking Guide:',
            '1. **Reservations Tab**: Go to the admin panel and navigate to the "Reservations" page to log a walk-in guest.',
            '2. **Fill Details**: Select room, check-in/check-out dates, and input guest name, email, and phone number.',
            '3. **Manage Status & Operations**: To confirm, check-in, check-out, extend a stay, or cancel, go to the **Booking Records** page and click the **Manage** button on the booking row to open the controls modal.',
        ];

        return $this->reply($lines, 'customer-booking');
    }

    private function hotelProfileReply(): array
    {
        $foundedYear = $this->hotelProfile['founded_year'] ?? null;
        $lines = [];

        if ($foundedYear) {
            $yearsRunning = max(0, (int) date('Y') - (int) $foundedYear);
            $lines[] = sprintf('%s was founded in %s and has been running for about %d year(s).', $this->hotelProfile['name'], $foundedYear, $yearsRunning);
        } else {
            $lines[] = (string) ($this->hotelProfile['founded_note'] ?? 'Founding information is not recorded yet.');
            $lines[] = 'Add the founding year in `app/config/hotel.php` so support can answer it directly.';
        }

        $lines[] = '';
        $lines[] = 'Hotel identity:';
        $lines[] = '- Name: ' . (string) $this->hotelProfile['name'];
        $lines[] = '- Description: ' . (string) $this->hotelProfile['description'];
        $lines[] = '- Support Email: ' . (string) ($this->hotelProfile['support_email'] ?: 'support@example.com');
        $lines[] = '- Support Phone: ' . (string) ($this->hotelProfile['support_phone'] ?: 'Not provided');

        return $this->reply($lines, 'customer-hotel-profile');
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

        $lines = [
            'Admin overview:',
            sprintf('- Customers this month: %d', (int) $dashboardSummary['customers_this_month']),
            sprintf('- Pending reservations: %d', (int) $dashboardSummary['pending_reservations']),
            sprintf('- Upcoming check-outs: %d', (int) $dashboardSummary['upcoming_checkouts']),
            sprintf('- Available rooms: %d', (int) $roomSummary['available']),
            sprintf('- Rooms not available: %d', (int) $roomSummary['not_available']),
            sprintf('- Confirmed revenue this month: %s', formatMoney($revenueThisMonth)),
            '',
            'Monthly sales graph data:',
        ];

        foreach ($monthlyPerformance as $row) {
            $lines[] = sprintf(
                '- %s | bookings: %d | income: %s',
                $row['month_label'],
                (int) $row['rooms_booked'],
                formatMoney((float) $row['income'])
            );
        }

        if ($range) {
            $report = $this->paymentModel->revenueReport($range['start'], $range['end']);
            $trend = $this->reservationModel->reservationTrendReport($range['start'], $range['end']);
            $occupancy = $this->reservationModel->occupancyReport($range['start'], $range['end']);

            $lines[] = '';
            $lines[] = sprintf('Report range %s to %s:', $range['start'], $range['end']);
            $lines[] = sprintf('- Confirmed revenue: %s', formatMoney((float) $report['total_revenue']));
            $lines[] = sprintf('- Reservations created: %d', (int) $trend['total_reservations']);
            $lines[] = sprintf('- Occupancy rate: %s', number_format((float) $occupancy['occupancy_rate'], 1) . '%');
        }

        return $this->reply($lines, 'admin-overview');
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

        $topRoomType = null;
        $topRevenue = 0.0;

        foreach ($revenueReport['by_room_type'] as $row) {
            $revenue = (float) $row['confirmed_revenue'];
            if ($revenue > $topRevenue) {
                $topRevenue = $revenue;
                $topRoomType = (string) $row['room_type'];
            }
        }

        $lines = [
            'Admin statistics:',
            sprintf('- Customers this month: %d', (int) $dashboardSummary['customers_this_month']),
            sprintf('- Pending reservations: %d', (int) $dashboardSummary['pending_reservations']),
            sprintf('- Available rooms: %d', (int) $roomSummary['available']),
            sprintf('- Rooms not available: %d', (int) $roomSummary['not_available']),
            sprintf('- Confirmed revenue for range: %s', formatMoney((float) $revenueReport['total_revenue'])),
            sprintf('- Reservations created for range: %d', (int) $trendReport['total_reservations']),
            sprintf('- Occupancy rate for range: %s', number_format((float) $occupancyReport['occupancy_rate'], 1) . '%'),
        ];

        if ($topRoomType !== null) {
            $lines[] = sprintf('- Top room type by revenue: %s (%s)', $topRoomType, formatMoney($topRevenue));
        }

        $lines[] = '';
        $lines[] = 'Room inventory by type:';
        $lines[] = '| Type | Total rooms | Available | Lowest price |';
        $lines[] = '| --- | --- | --- | --- |';

        foreach ($typeSummary as $roomType => $summary) {
            $lines[] = sprintf(
                '| %s | %d | %d | %s |',
                $roomType,
                (int) $summary['total'],
                (int) $summary['available'],
                formatMoney((float) $summary['lowest_price']) . ' / night'
            );
        }

        $lines[] = '';
        $lines[] = 'Monthly performance snapshot:';
        foreach ($monthlyPerformance as $row) {
            $lines[] = sprintf(
                '- %s | bookings: %d | income: %s',
                $row['month_label'],
                (int) $row['rooms_booked'],
                formatMoney((float) $row['income'])
            );
        }

        if ($this->matchesAny($text, ['today', 'today statistics', 'daily statistics', 'today revenue'])) {
            $today = new DateTimeImmutable('today');
            $todayRange = [
                'start' => $today->format('Y-m-d'),
                'end' => $today->format('Y-m-d'),
            ];
            $todayRevenue = $this->paymentModel->revenueReport($todayRange['start'], $todayRange['end']);
            $todayTrend = $this->reservationModel->reservationTrendReport($todayRange['start'], $todayRange['end']);
            $todayOccupancy = $this->reservationModel->occupancyReport($todayRange['start'], $todayRange['end']);

            $lines[] = '';
            $lines[] = 'Today statistics:';
            $lines[] = sprintf('- Revenue today: %s', formatMoney((float) $todayRevenue['total_revenue']));
            $lines[] = sprintf('- Reservations today: %d', (int) $todayTrend['total_reservations']);
            $lines[] = sprintf('- Occupancy today: %s', number_format((float) $todayOccupancy['occupancy_rate'], 1) . '%');
        }

        return $this->reply($lines, 'admin-statistics');
    }

    private function adminSalesReply(array $range): array
    {
        if (!$range) {
            $range = $this->defaultRange();
        }

        $report = $this->paymentModel->revenueReport($range['start'], $range['end']);
        $trend = $this->reservationModel->reservationTrendReport($range['start'], $range['end']);
        $occupancy = $this->reservationModel->occupancyReport($range['start'], $range['end']);

        $lines = [
            sprintf('Sales report for %s to %s:', $range['start'], $range['end']),
            sprintf('- Confirmed revenue: %s', formatMoney((float) $report['total_revenue'])),
            sprintf('- Reservations created: %d', (int) $trend['total_reservations']),
            sprintf('- Occupancy rate: %s', number_format((float) $occupancy['occupancy_rate'], 1) . '%'),
            '',
            'Revenue by room type:',
        ];

        foreach ($report['by_room_type'] as $row) {
            $lines[] = sprintf(
                '- %s | payments: %d | revenue: %s',
                $row['room_type'],
                (int) $row['payment_count'],
                formatMoney((float) $row['confirmed_revenue'])
            );
        }

        return $this->reply($lines, 'admin-sales');
    }

    private function adminOperationsReply(array $range): array
    {
        $alerts = $this->reservationModel->operationalAlerts();
        $reportRange = $range ?? $this->defaultRange();
        $occupancy = $this->reservationModel->occupancyReport($reportRange['start'], $reportRange['end']);
        $trend = $this->reservationModel->reservationTrendReport($reportRange['start'], $reportRange['end']);

        $lines = [
            'Admin operations:',
            sprintf('- Overdue check-outs: %d', count($alerts['overdue_checkouts'])),
            sprintf('- Overbooking conflicts: %d', count($alerts['overbooking_conflicts'])),
            sprintf('- Reservations in range: %d', (int) $trend['total_reservations']),
            sprintf('- Occupancy rate in range: %s', number_format((float) $occupancy['occupancy_rate'], 1) . '%'),
            '',
            'Recent alerts:',
        ];

        foreach ($alerts['overdue_checkouts'] as $row) {
            $lines[] = sprintf(
                '- Overdue room %s | %s %s | due %s',
                $row['room_number'],
                $row['first_name'],
                $row['last_name'],
                $row['check_out']
            );
        }

        if (!$alerts['overdue_checkouts']) {
            $lines[] = '- No overdue check-outs right now.';
        }

        return $this->reply($lines, 'admin-operations');
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

    private function findBestDatasetMatch(string $normalizedMessage): ?array
    {
        $bestEntry = null;
        $bestScore = 0.0;
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

                if ($input === $normalizedPattern) {
                    $score = 1.0;
                } else {
                    $patternWords = count(explode(' ', $normalizedPattern));
                    $inputWords = count(explode(' ', $input));
                    $coverage = $patternWords / max($inputWords, 1);
                    
                    $score = 0.55 + ($coverage * 0.25) + (strlen($normalizedPattern) / 80);
                    $score = min(1.0, $score);
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestEntry = $entry;
                }
            }
        }

        return $bestEntry && $bestScore >= 0.55 ? $bestEntry : null;
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
        $cancellationLossFormatted = formatMoney((float) ($analytics['cancellation_revenue_loss'] ?? 0));
        $loyaltyRatio = number_format((float) ($analytics['repeat_guest_loyalty_ratio'] ?? 0), 1);

        $html = "
        <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.4); border-radius: 12px; padding: 14px;'>
            <div style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:15px; margin-bottom:8px;'>📊 Executive Hospitality Performance</div>
            <div style='display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:12px;'>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:8px; border-radius:8px;'>
                    <span style='color:#94a3b8; display:block;'>Average Stay (ALOS)</span>
                    <strong style='color:#ffdf73; font-size:14px;'>{$alos} Nights</strong>
                </div>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:8px; border-radius:8px;'>
                    <span style='color:#94a3b8; display:block;'>Booking Lead Time</span>
                    <strong style='color:#ffdf73; font-size:14px;'>{$leadTime} Days</strong>
                </div>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:8px; border-radius:8px;'>
                    <span style='color:#94a3b8; display:block;'>Cancellation Rate</span>
                    <strong style='color:#f87171; font-size:14px;'>{$cancellationRate}%</strong>
                    <span style='color:#f87171; font-size:10px; display:block;'>({$cancellationLossFormatted} lost)</span>
                </div>
                <div style='background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); padding:8px; border-radius:8px;'>
                    <span style='color:#94a3b8; display:block;'>Repeat Guest Loyalty</span>
                    <strong style='color:#4ade80; font-size:14px;'>{$loyaltyRatio}%</strong>
                </div>
            </div>
            <a href='../admin/reports.php' style='display:block; text-align:center; background:linear-gradient(135deg, #D4AF37, #FFDF73); color:#020617; font-weight:bold; padding:7px 12px; border-radius:8px; text-decoration:none; margin-top:10px; font-size:12px;'>Open Full Executive Reports &rarr;</a>
        </div>";

        return [
            'text' => $html,
            'kind' => 'admin-forecast',
            'quick_chips' => ['📈 Monthly Sales', '🧹 Dirty Rooms', '🚨 Pending Risks', '📊 Room Occupancy'],
        ];
    }

    private function adminHousekeepingReply(): array
    {
        $sql = "SELECT room_id, room_number, room_type, floor, status FROM rooms WHERE status IN ('Cleaning', 'Maintenance') ORDER BY floor ASC, room_number ASC";
        $stmt = $this->db->query($sql);
        $dirtyRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$dirtyRooms) {
            $html = "<div style='background: rgba(15,23,42,0.9); border: 1px solid rgba(34,197,94,0.4); border-radius: 12px; padding: 14px; text-align: center;'>
                        <strong style='color:#4ade80;'>All Rooms Clean & Ready!</strong>
                        <p style='font-size:12px; color:#94a3b8; margin: 4px 0 0 0;'>Zero rooms currently require housekeeping or maintenance.</p>
                     </div>";
            return [
                'text' => $html,
                'kind' => 'admin-housekeeping',
                'quick_chips' => ['📊 Executive Forecast', '📈 Monthly Sales', '🏨 Room Occupancy'],
            ];
        }

        $html = "<div style='margin-bottom:8px; font-weight:bold; color:#ffdf73;'>🧹 Housekeeping & Maintenance Watchlist (" . count($dirtyRooms) . " Rooms):</div>";
        $html .= "<div style='display:flex; flex-direction:column; gap:8px;'>";

        foreach ($dirtyRooms as $room) {
            $roomNum = htmlspecialchars((string) $room['room_number']);
            $suiteType = htmlspecialchars((string) $room['room_type']);
            $status = htmlspecialchars((string) $room['status']);
            $floor = (int) $room['floor'];
            $badgeColor = $status === 'Cleaning' ? 'background:rgba(234,179,8,0.2); color:#eab308; border:1px solid rgba(234,179,8,0.4);' : 'background:rgba(239,68,68,0.2); color:#ef4444; border:1px solid rgba(239,68,68,0.4);';

            $html .= "
            <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.3); border-radius: 10px; padding: 10px 12px; display:flex; justify-content:space-between; align-items:center;'>
                <div>
                    <strong style='color:#ffdf73; font-size:13px;'>Room #{$roomNum}</strong>
                    <span style='font-size:11px; color:#94a3b8; display:block;'>Floor {$floor} &bull; {$suiteType}</span>
                </div>
                <span style='{$badgeColor} padding:2px 8px; border-radius:99px; font-size:11px; font-weight:bold;'>{$status}</span>
            </div>";
        }
        $html .= "</div>";
        $html .= "<a href='../admin/rooms.php' style='display:block; text-align:center; background:rgba(212,175,55,0.15); color:#ffdf73; border:1px solid rgba(212,175,55,0.4); font-weight:bold; padding:6px 12px; border-radius:8px; text-decoration:none; margin-top:8px; font-size:12px;'>Manage Housekeeping Status &rarr;</a>";

        return [
            'text' => $html,
            'kind' => 'admin-housekeeping',
            'quick_chips' => ['📊 Executive Forecast', '📈 Monthly Sales', '🚨 Pending Risks'],
        ];
    }

    private function customerConciergeServicesReply(): array
    {
        $html = "
        <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.4); border-radius: 12px; padding: 14px;'>
            <div style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:15px; margin-bottom:8px;'>🛎️ Emperor Luxury Concierge Services</div>
            <div style='font-size:12px; color:#cbd5e1; display:flex; flex-direction:column; gap:6px;'>
                <div>🏊 <strong>Rooftop Infinity Pool</strong>: Open daily 6:00 AM – 10:00 PM (Complimentary)</div>
                <div>💆 <strong>Emperor Royal Spa</strong>: Aromatherapy & Swedish massage (8:00 AM – 11:00 PM)</div>
                <div>🍳 <strong>Skyline Breakfast Buffet</strong>: Served 6:30 AM – 10:30 AM at Grand Dining Room</div>
                <div>🚗 <strong>Airport Express Transfer</strong>: 24/7 Concierge Chauffeur booking available</div>
            </div>
            <a href='../site/contact.php' style='display:block; text-align:center; background:linear-gradient(135deg, #D4AF37, #FFDF73); color:#020617; font-weight:bold; padding:7px 12px; border-radius:8px; text-decoration:none; margin-top:10px; font-size:12px;'>Send Inquiry to Concierge Desk &rarr;</a>
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
            $html = "<div style='background: rgba(15,23,42,0.9); border: 1px solid rgba(212,175,55,0.4); border-radius: 12px; padding: 14px; text-align: center;'>
                        <strong style='color:#ffdf73;'>Please Log In</strong>
                        <p style='font-size:12px; color:#94a3b8; margin: 4px 0 10px 0;'>Log in to your account to view your active bookings and printable receipts.</p>
                        <a href='../auth/login.php' style='display:inline-block; background:linear-gradient(135deg, #D4AF37, #FFDF73); color:#020617; font-weight:bold; padding:6px 16px; border-radius:8px; text-decoration:none; font-size:12px;'>Log In Now &rarr;</a>
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
            $html = "<div style='background: rgba(15,23,42,0.9); border: 1px solid rgba(212,175,55,0.4); border-radius: 12px; padding: 14px; text-align: center;'>
                        <strong style='color:#ffdf73;'>No Active Reservations Found</strong>
                        <p style='font-size:12px; color:#94a3b8; margin: 4px 0 10px 0;'>You haven't placed any bookings yet. Pick a room to start!</p>
                        <a href='../user/dashboard.php' style='display:inline-block; background:linear-gradient(135deg, #D4AF37, #FFDF73); color:#020617; font-weight:bold; padding:6px 16px; border-radius:8px; text-decoration:none; font-size:12px;'>Book a Suite Now &rarr;</a>
                     </div>";
            return [
                'text' => $html,
                'kind' => 'customer-lookup',
                'quick_chips' => ['📅 Available Rooms', '💰 Suite Rates', '🛎️ Concierge Desk'],
            ];
        }

        $html = "<div style='margin-bottom:8px; font-weight:bold; color:#ffdf73;'>📋 Your Recent Reservations:</div>";
        $html .= "<div style='display:flex; flex-direction:column; gap:10px;'>";

        foreach ($reservations as $res) {
            $resId = (int) $res['reservation_id'];
            $roomNum = htmlspecialchars((string) $res['room_number']);
            $suiteType = htmlspecialchars((string) $res['room_type']);
            $status = htmlspecialchars((string) $res['status']);
            $totalFormatted = formatMoney((float) $res['total_amount']);
            $checkIn = htmlspecialchars((string) $res['check_in']);
            $checkOut = htmlspecialchars((string) $res['check_out']);

            $badgeColor = match ($status) {
                'Confirmed', 'Checked-in' => 'background:rgba(34,197,94,0.2); color:#4ade80; border:1px solid rgba(34,197,94,0.4);',
                'Pending' => 'background:rgba(234,179,8,0.2); color:#eab308; border:1px solid rgba(234,179,8,0.4);',
                default => 'background:rgba(148,163,184,0.2); color:#cbd5e1; border:1px solid rgba(148,163,184,0.4);',
            };

            $html .= "
            <div style='background: rgba(15,23,42,0.95); border: 1px solid rgba(212,175,55,0.4); border-radius: 12px; padding: 12px 14px;'>
                <div style='display:flex; justify-content:space-between; align-items:center;'>
                    <span style='color:#ffdf73; font-weight:bold; font-family:serif; font-size:13px;'>Booking #{$resId} &bull; Room #{$roomNum}</span>
                    <span style='{$badgeColor} padding:2px 8px; border-radius:99px; font-size:11px; font-weight:bold;'>{$status}</span>
                </div>
                <div style='font-size:12px; color:#cbd5e1; margin: 4px 0;'>{$suiteType} &bull; {$checkIn} to {$checkOut}</div>
                <div style='font-size:12px; color:#ffdf73; font-weight:bold;'>Total: {$totalFormatted}</div>
                <a href='../user/receipt.php?reservation_id={$resId}' target='_blank' style='display:block; text-align:center; background:rgba(212,175,55,0.15); color:#ffdf73; border:1px solid rgba(212,175,55,0.4); font-weight:bold; padding:5px 10px; border-radius:6px; text-decoration:none; margin-top:8px; font-size:11px;'>📄 View Printable Receipt &rarr;</a>
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
