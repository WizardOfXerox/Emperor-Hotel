<?php

declare(strict_types=1);

require_once __DIR__ . '/room_catalog.php';

function renderRoomShowcaseSection(): void
{
    $user = currentUser();
    $catalog = roomCatalog();
    $roomStats = [];
    $roomDataUnavailable = false;

    try {
        $roomModel = new Room(Database::connect());
        $roomStats = $roomModel->typeSummary();
    } catch (Throwable) {
        $roomDataUnavailable = true;
    }

    $reservationHref = '../auth/login.php';
    $reservationLabel = 'LOG IN TO RESERVE';

    if ($user) {
        $isAdmin = $user['role'] === 'admin';
        $reservationHref = $isAdmin ? '../admin/reservations.php' : '../user/dashboard.php';
        $reservationLabel = $isAdmin ? 'MANAGE RESERVATIONS' : 'RESERVE NOW';
    }

    $roomPresentation = [
        'Imperial Deluxe' => [
            'category' => 'STANDARD ROOM',
            'heading' => 'IMPERIAL DELUXE',
            'tagline' => 'Where Smart Comfort Meets Timeless Elegance',
            'cards' => [
                ['title' => 'Smart Living', 'items' => ['Smart bedside lighting control', 'Automated curtain system', 'Digital climate control', 'Wireless charging station']],
                ['title' => 'Comfort & Design', 'items' => ['King-size premium bed', 'Layered luxury bedding', 'Executive workspace', 'Floor-to-ceiling skyline windows']],
                ['title' => 'Signature Design', 'items' => ['Charcoal textured walls', 'Walnut wood paneling', 'Brushed gold accents', 'Black marble furnishings']],
            ],
        ],
        'Royal Executive' => [
            'category' => 'FAMILY ROOM',
            'heading' => 'ROYAL EXECUTIVE',
            'tagline' => 'Where Leadership Meets Intelligent Luxury',
            'cards' => [
                ['title' => 'Smart Executive', 'items' => ['Voice-controlled room assistant', 'Programmable mood lighting', 'Motorized blackout curtains', 'Digital privacy glass controls']],
                ['title' => 'Comfort & Space', 'items' => ['Spacious king-size premium bed', 'Dedicated executive workspace', 'Separate private lounge', 'Floor-to-ceiling skyline windows', 'Premium in-room minibar']],
                ['title' => 'Signature Design', 'items' => ['Charcoal stone walls', 'Walnut wood paneling', 'Brushed gold accents', 'Black marble furnishings']],
            ],
        ],
        'Emperor Presidential' => [
            'category' => 'SIGNATURE ROOM',
            'heading' => 'EMPEROR PRESIDENTIAL',
            'tagline' => 'Where Absolute Luxury Becomes Personal',
            'cards' => [
                ['title' => 'Smart Future', 'items' => ['Full AI room orchestration hub', 'Biometric private suite access', 'Digital concierge display', 'Intelligent climate zoning', 'Personalized scene presets', 'Switchable smart glass walls']],
                ['title' => 'Comfort & Space', 'items' => ['Master king-size premium bed', 'Private executive office', 'Exclusive lounge area', 'Private dining space', 'Designer walk-in wardrobe', 'Private signature bar']],
                ['title' => 'Signature Design', 'items' => ['Double-height luxury ceiling', 'Panoramic skyline glass walls', 'Charcoal stone walls', 'Walnut wood paneling', 'Brushed gold accents', 'Black marble furnishings']],
            ],
        ],
    ];

    echo '<section class="site-section" id="suites-rooms">';
    echo '<div class="container">';
    echo '<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">';
    echo '<div>';
    echo '<p class="eyebrow mb-2">Suites & Rooms</p>';
    echo '<h2 class="section-title mb-0">Explore room types and live availability</h2>';
    echo '</div>';
    echo '<a class="btn btn-warning fw-semibold" href="' . e($reservationHref) . '">' . e($reservationLabel) . '</a>';
    echo '</div>';

    if ($roomDataUnavailable) {
        echo '<div class="rooms-notice mb-4"><p>Room pricing is temporarily unavailable. The gallery and room details are still available below.</p></div>';
    }

    foreach ($catalog as $roomType => $roomInfo) {
        $presentation = $roomPresentation[$roomType] ?? [
            'category' => strtoupper($roomType),
            'heading' => strtoupper($roomType),
            'tagline' => $roomInfo['tagline'],
            'cards' => [],
        ];
        $includedPerks = roomIncludedPerksText($roomType);
        $stats = $roomStats[$roomType] ?? ['available' => 0, 'total' => 0, 'lowest_price' => 0.0];
        $priceText = 'PRICE NOT SET';

        if ($roomDataUnavailable) {
            $priceText = 'PRICING UNAVAILABLE';
        } elseif ((float) $stats['lowest_price'] > 0) {
            $priceText = formatMoney((float) $stats['lowest_price']) . ' / NIGHT';
        }

        echo '<section class="rooms">';
        echo '<div class="container-carousel">';
        echo '<div class="carousel" data-carousel>';
        echo '<button class="carousel-control prev" type="button" data-carousel-prev aria-label="Previous ' . e($roomType) . ' image">&#10094;</button>';

        foreach ($roomInfo['carousel'] as $slideIndex => $imagePath) {
            echo '<div class="carousel-slide' . ($slideIndex === 0 ? ' is-active' : '') . '">';
            echo '<img src="' . e($imagePath) . '" alt="' . e($roomType . ' room view ' . ($slideIndex + 1)) . '">';
            echo '</div>';
        }

        echo '<button class="carousel-control next" type="button" data-carousel-next aria-label="Next ' . e($roomType) . ' image">&#10095;</button>';
        echo '<div class="carousel-indicators">';
        foreach ($roomInfo['carousel'] as $slideIndex => $_) {
            echo '<button class="carousel-indicator' . ($slideIndex === 0 ? ' is-active' : '') . '" type="button" data-carousel-indicator="' . e((string) $slideIndex) . '" aria-label="Show ' . e($roomType) . ' image ' . e((string) ($slideIndex + 1)) . '"></button>';
        }
        echo '</div></div></div>';

        echo '<div class="container-content">';
        echo '<div class="content">';
        echo '<h2>' . e($presentation['category']) . '</h2>';
        echo '<h1>' . e($presentation['heading']) . '</h1>';
        echo '<p>' . e($presentation['tagline']) . '</p>';
        echo '<p class="room-inclusion-line">Comes with: ' . e($includedPerks) . '.</p>';
        echo '</div>';

        echo '<div class="container-card">';
        foreach ($presentation['cards'] as $card) {
            echo '<article class="card"><div class="card-content">';
            echo '<p>' . e($card['title']) . '</p><hr><ul>';
            foreach ($card['items'] as $item) {
                echo '<li>' . e($item) . '</li>';
            }
            echo '</ul></div></article>';
        }
        echo '</div>';

        echo '<div class="room-actions">';
        echo '<a class="room-price room-price--booking" href="' . e($reservationHref) . '" aria-label="' . e($reservationLabel . ' - ' . $roomType . ' from ' . $priceText) . '">' . e($priceText) . '</a>';
        echo '</div>';
        echo '</div></section>';
    }

    echo '</div></section>';
}
