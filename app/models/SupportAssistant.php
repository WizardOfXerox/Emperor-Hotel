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
        $lines = ['Available rooms right now:'];

        if (!$availableRooms) {
            $lines[] = 'No rooms are marked Available at the moment.';
        } else {
            $lines[] = '';
            $lines[] = '| Room | Type | Floor | Price / night |';
            $lines[] = '| --- | --- | --- | --- |';

            foreach ($availableRooms as $room) {
                $lines[] = sprintf(
                    '| %s | %s | %s | %s |',
                    $room['room_number'],
                    $room['room_type'],
                    $room['floor'],
                    formatMoney((float) $room['price_per_night'])
                );
            }
        }

        return $this->reply($lines, 'customer-availability-table');
    }

    private function customerRoomTypeReply(): array
    {
        $typeSummary = $this->roomModel->typeSummary();
        $roomCatalog = roomCatalog();
        $lines = ['Room types and inclusions:'];

        $lines[] = '';
        $lines[] = '| Type | Total rooms | Available | Lowest price |';
        $lines[] = '| --- | --- | --- | --- |';

        foreach ($roomCatalog as $roomType => $roomInfo) {
            $summary = $typeSummary[$roomType] ?? null;
            $availableCount = $summary ? (int) $summary['available'] : 0;
            $totalCount = $summary ? (int) $summary['total'] : 0;
            $priceText = $summary
                ? formatMoney((float) $summary['lowest_price']) . ' / night'
                : 'Price not set';

            $lines[] = sprintf(
                '| %s | %d | %d | %s |',
                $roomType,
                $totalCount,
                $availableCount,
                $priceText
            );
        }

        $lines[] = '';
        $lines[] = 'Included perks by room type:';

        foreach ($roomCatalog as $roomType => $roomInfo) {
            $lines[] = sprintf('- %s: %s', $roomType, implode(', ', $roomInfo['included_perks']));
        }

        return $this->reply($lines, 'customer-room-types-table');
    }

    private function customerRoomPriceReply(): array
    {
        $typeSummary = $this->roomModel->typeSummary();
        $roomCatalog = roomCatalog();
        $lines = ['Room prices by type:'];
        $lines[] = '';
        $lines[] = '| Type | Lowest price | Availability |';
        $lines[] = '| --- | --- | --- |';

        foreach ($roomCatalog as $roomType => $roomInfo) {
            $summary = $typeSummary[$roomType] ?? null;
            $priceText = $summary
                ? formatMoney((float) $summary['lowest_price']) . ' / night'
                : 'Price not set';
            $availableCount = $summary ? (int) $summary['available'] : 0;
            $totalCount = $summary ? (int) $summary['total'] : 0;

            $lines[] = sprintf(
                '| %s | %s | %d of %d available |',
                $roomType,
                $priceText,
                $availableCount,
                $totalCount
            );
        }

        $lines[] = '';
        $lines[] = 'If you want, I can also show availability for a specific room type or floor.';

        return $this->reply($lines, 'customer-room-prices-table');
    }

    private function customerBookingReply(): array
    {
        $lines = [
            'Customer support can help with room availability, room pricing, and booking guidance.',
            'For a reservation, choose your stay dates, pick a room, then complete the booking form from your dashboard.',
            'If you want, ask me for: available rooms, room prices, or hotel history.',
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
        return $this->matchesAny($text, ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening']);
    }

    private function hasCustomerIntent(string $text): bool
    {
        return $this->matchesAny($text, ['room', 'suite', 'price', 'booking', 'reservation', 'hotel', 'history', 'founding', 'check in', 'check out', 'payment']);
    }

    private function hasAdminIntent(string $text): bool
    {
        return $this->matchesAny($text, ['dashboard', 'sales', 'revenue', 'income', 'graph', 'chart', 'occupancy', 'report', 'reservations', 'payments', 'alerts', 'room stats']);
    }

    private function findBestDatasetMatch(string $normalizedMessage): ?array
    {
        $bestEntry = null;
        $bestScore = 0.0;
        $input = $normalizedMessage;

        foreach ($this->datasetEntries() as $entry) {
            foreach ($entry['patterns'] as $pattern) {
                $normalizedPattern = $this->normalizeText((string) $pattern);

                if ($normalizedPattern === '' || !str_contains($input, $normalizedPattern)) {
                    continue;
                }

                $coverage = count(explode(' ', $normalizedPattern)) / max(count(explode(' ', $input)), 1);
                $score = min(1.0, $coverage + strlen($normalizedPattern) / 40);

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
        ];
    }

    private function composeAiContext(string $scope, array $range, array $keywords): string
    {
        $lines = [];

        if ($scope === 'admin') {
            $lines[] = 'Admin support context:';
            $lines[] = '- Current dashboard summary is available from live hotel data.';
            $lines[] = '- If a date range is present, use report data for that range.';
            $lines[] = '- If the user asks for charts, summarize revenue, occupancy, reservations, and room status.';
            $lines[] = '- If the user asks about room availability, room types, or room pricing, use a markdown table in its own paragraph block.';
        } else {
            $lines[] = 'Customer support context:';
            $lines[] = '- Available rooms, room types, prices, and hotel info are available from live hotel data.';
            $lines[] = '- Stay within customer-facing answers unless the user explicitly asks for admin information.';
            $lines[] = '- If the user asks about rooms, types, or pricing, use a markdown table in its own paragraph block.';
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

        return null;
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
