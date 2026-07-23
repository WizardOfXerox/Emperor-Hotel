<?php

declare(strict_types=1);

function roomCatalog(): array
{
    return [
        'Imperial Deluxe' => [
            'slug' => 'imperial-deluxe',
            'hero' => '../assets/images/rooms/imperial-deluxe/hero.jpg',
            'carousel' => [
                '../assets/images/rooms/imperial-deluxe/carousel/1.jpg',
                '../assets/images/rooms/imperial-deluxe/carousel/2.jpg',
                '../assets/images/rooms/imperial-deluxe/carousel/3.jpg',
            ],
            'tagline' => 'Where smart comfort meets timeless elegance.',
            'details' => 'A polished 38 sqm (409 sq ft) suite crafted for guests who appreciate warm luxury styling and efficient space. Features a plush Queen or King Bed, smart bedside lighting controls, dedicated work desk, and a modern rainfall shower vanity area.',
            'ideal_for' => 'Couples, solo travelers, and business guests (Up to 2 Guests)',
            'bed_options' => ['Queen Bed', 'King Bed'],
            'max_capacity' => 2,
            'view_type' => 'City Skyline View',
            'dimensions' => '38 sqm / 409 sq ft',
            'included_perks' => [
                'Complimentary breakfast set',
                'High-speed priority Wi-Fi',
            ],
            'features' => [
                'Plush Queen or King bed with 400-thread luxury linen',
                'Ergonomic executive work desk & lounge chair',
                'Modern rainfall shower and Italian marble vanity',
                'Smart TV, digital climate control, and minibar',
            ],
        ],
        'Royal Executive' => [
            'slug' => 'royal-executive-suite',
            'hero' => '../assets/images/rooms/royal-executive-suite/hero.jpg',
            'carousel' => [
                '../assets/images/rooms/royal-executive-suite/carousel/1.jpg',
                '../assets/images/rooms/royal-executive-suite/carousel/2.jpg',
                '../assets/images/rooms/royal-executive-suite/carousel/3.jpg',
            ],
            'tagline' => 'Where leadership meets intelligent luxury.',
            'details' => 'An expanded 58 sqm (624 sq ft) executive suite designed for productivity and extended stays. Features a King Bed with optional twin setup, separate private lounge seating, executive desk, and full breakfast buffet access.',
            'ideal_for' => 'Executives, long-stay guests, and premium business trips (Up to 4 Guests)',
            'bed_options' => ['King Bed', 'Twin Beds'],
            'max_capacity' => 4,
            'view_type' => 'Garden Terrace & City View',
            'dimensions' => '58 sqm / 624 sq ft',
            'included_perks' => [
                'Full breakfast buffet access',
                'Priority Wi-Fi & Lounge Access',
                'Express Laundry Discount',
            ],
            'features' => [
                'Spacious sleeping area and separate executive sitting lounge',
                'Executive workstation with universal media hub',
                'Deep soaking tub and separate rainfall shower',
                'Voice-controlled room assistant and blackout motorized curtains',
            ],
        ],
        'Emperor Presidential' => [
            'slug' => 'emperor-presidential-suite',
            'hero' => '../assets/images/rooms/emperor-presidential-suite/hero.jpg',
            'carousel' => [
                '../assets/images/rooms/emperor-presidential-suite/carousel/1.jpg',
                '../assets/images/rooms/emperor-presidential-suite/carousel/2.jpg',
                '../assets/images/rooms/emperor-presidential-suite/carousel/3.jpg',
            ],
            'tagline' => 'Where absolute luxury becomes personal.',
            'details' => 'The crown jewel of Emperor Hotel. A grand 110 sqm (1,184 sq ft) master suite with a Super King Bed, double-height ceilings, private dining room, panoramic floor-to-ceiling glass walls, VIP car shuttle transfer, and butler service.',
            'ideal_for' => 'VIP guests, family celebrations, and luxury stays (Up to 6 Guests)',
            'bed_options' => ['Super King Master Suite'],
            'max_capacity' => 6,
            'view_type' => 'Panoramic Ocean & City Skyline View',
            'dimensions' => '110 sqm / 1,184 sq ft',
            'included_perks' => [
                'Full breakfast buffet access',
                'Complimentary private airport shuttle',
                'Late checkout guaranteed (4 PM)',
                'Dedicated 24/7 Butler Assistance',
            ],
            'features' => [
                'Super King master bed & separate connecting suite access',
                'Private living room lounge and 6-person dining area',
                'Double-height ceiling with custom chandelier',
                'Oversized Jacuzzi spa bath with skyline ocean views',
            ],
        ],
    ];
}

function individualRoomCatalog(): array
{
    // Per-room specific catalog overrides (keyed by room_number or room_id)
    return [
        '101' => [
            'tagline' => 'Imperial Deluxe #101 — Corner Courtyard Suite',
            'details' => 'Room #101 offers a quiet first-floor corner location with direct courtyard garden views, upgraded luxury linen, and an ergonomic workstation.',
            'view_type' => 'Courtyard Garden View',
        ],
        '102' => [
            'tagline' => 'Imperial Deluxe #102 — Most Booked Guest Choice',
            'details' => 'Room #102 features panoramic floor-to-ceiling glass, extra-quiet acoustic insulation, and rapid room service access.',
            'view_type' => 'City Skyline View',
        ],
        '201' => [
            'tagline' => 'Royal Executive #201 — Terrace & Lounge Suite',
            'details' => 'Room #201 features an expanded private terrace, dual rainfall showers, and executive lounge privileges.',
            'view_type' => 'Private Garden Terrace View',
        ],
        '301' => [
            'tagline' => 'Emperor Presidential #301 — Top-Floor Grand Penthouse',
            'details' => 'Room #301 is our flagship 110 sqm top-floor penthouse with double-height ceilings, private dining room, and dedicated 24/7 butler service.',
            'view_type' => 'Panoramic Ocean & City View',
        ],
    ];
}

function getRoomCatalogData(array|string|int $roomOrType): array
{
    $catalog = roomCatalog();
    $perRoom = individualRoomCatalog();

    if (is_array($roomOrType)) {
        $roomNum = (string)($roomOrType['room_number'] ?? '');
        $roomId = (string)($roomOrType['room_id'] ?? '');
        $type = (string)($roomOrType['room_type'] ?? 'Imperial Deluxe');

        $baseTypeCatalog = $catalog[$type] ?? $catalog['Imperial Deluxe'];
        $override = $perRoom[$roomNum] ?? ($perRoom[$roomId] ?? []);

        $merged = array_merge($baseTypeCatalog, array_filter($override));

        if (!empty($roomOrType['image_url'])) {
            $rawImg = trim((string)$roomOrType['image_url']);
            $images = [];
            if (str_starts_with($rawImg, '[')) {
                $decoded = json_decode($rawImg, true);
                if (is_array($decoded)) {
                    $images = array_filter(array_map('trim', $decoded));
                }
            }
            if (empty($images)) {
                $images = array_filter(array_map('trim', explode(',', $rawImg)));
            }

            if (!empty($images)) {
                $images = array_values($images);
                $merged['hero'] = $images[0];
                $merged['carousel'] = array_slice($images, 1);
            }
        }

        return $merged;
    }

    $strKey = (string)$roomOrType;
    if (isset($catalog[$strKey])) {
        return $catalog[$strKey];
    }
    if (isset($perRoom[$strKey])) {
        return array_merge($catalog['Imperial Deluxe'], $perRoom[$strKey]);
    }

    return $catalog['Imperial Deluxe'];
}

function roomIncludedPerksForType(string $roomType): array
{
    $catalog = roomCatalog();

    return array_values($catalog[$roomType]['included_perks'] ?? []);
}

function roomIncludedPerksText(string $roomType): string
{
    $perks = roomIncludedPerksForType($roomType);

    return $perks ? implode(', ', $perks) : 'Standard room amenities';
}
