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
            'details' => 'A polished room made for guests who want a warm luxury feel without excess. It balances comfort, clean design, and practical amenities for both short stays and business visits.',
            'ideal_for' => 'Couples, solo travelers, and business guests',
            'features' => [
                'Plush king-size bed with premium linen',
                'Dedicated work desk and lounge corner',
                'Modern rainfall shower and vanity area',
                'Fast Wi-Fi, smart TV, and minibar access',
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
            'details' => 'Designed for guests who need more space, more privacy, and a stronger executive feel. This suite gives a more elevated stay with room to work, meet, and relax.',
            'ideal_for' => 'Executives, long-stay guests, and premium business trips',
            'features' => [
                'Expanded sleeping and sitting area',
                'Executive desk setup for productivity',
                'Premium bath finish and welcome amenities',
                'Refined lighting, storage, and entertainment setup',
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
            'details' => 'The signature stay experience of Emperor Hotel. This suite is meant to feel grand, private, and memorable, with larger spaces and a more dramatic luxury atmosphere.',
            'ideal_for' => 'VIP guests, family celebrations, and luxury stays',
            'features' => [
                'Large master bed and elegant lounge space',
                'Premium suite layout with statement interiors',
                'Dining and hosting-friendly room flow',
                'Top-tier comfort for special occasions and private stays',
            ],
        ],
    ];
}
