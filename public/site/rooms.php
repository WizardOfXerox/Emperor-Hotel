<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/room_catalog.php';

$user = currentUser();
$catalog = roomCatalog();
$db = null;
$roomStats = [];
$roomDataUnavailable = false;

try {
    $db = Database::connect();
    $roomModel = new Room($db);
    $roomStats = $roomModel->typeSummary();
} catch (Throwable) {
    $roomDataUnavailable = true;
}

$reservationHref = '../auth/login.php';
$reservationLabel = 'LOG IN TO RESERVE';
$authLinks = [
    [
        'href' => '../auth/login.php',
        'label' => 'LOG IN',
        'variant' => 'primary',
    ],
    [
        'href' => '../auth/register.php',
        'label' => 'REGISTER',
        'variant' => 'secondary',
    ],
];

if ($user) {
    $isAdmin = $user['role'] === 'admin';
    $reservationHref = $isAdmin ? '../admin/reservations.php' : '../user/dashboard.php';
    $reservationLabel = $isAdmin ? 'MANAGE RESERVATIONS' : 'RESERVE NOW';
    $authLinks = [
        [
            'href' => $isAdmin ? '../admin/dashboard.php' : '../user/dashboard.php',
            'label' => 'DASHBOARD',
            'variant' => 'primary',
        ],
        [
            'href' => '../auth/logout.php',
            'label' => 'LOG OUT',
            'variant' => 'secondary',
        ],
    ];
}

$roomPresentation = [
    'Imperial Deluxe' => [
        'category' => 'STANDARD ROOM',
        'heading' => 'IMPERIAL DELUXE',
        'tagline' => 'Where Smart Comfort Meets Timeless Elegance',
        'cards' => [
            [
                'title' => 'Smart Living',
                'items' => [
                    'Smart bedside lighting control',
                    'Automated curtain system',
                    'Digital climate control',
                    'Wireless charging station',
                ],
            ],
            [
                'title' => 'Comfort & Design',
                'items' => [
                    'King-size premium bed',
                    'Layered luxury bedding',
                    'Executive workspace',
                    'Floor-to-ceiling skyline windows',
                ],
            ],
            [
                'title' => 'Signature Design',
                'items' => [
                    'Charcoal textured walls',
                    'Walnut wood paneling',
                    'Brushed gold accents',
                    'Black marble furnishings',
                ],
            ],
        ],
    ],
    'Royal Executive' => [
        'category' => 'FAMILY ROOM',
        'heading' => 'ROYAL EXECUTIVE',
        'tagline' => 'Where Leadership Meets Intelligent Luxury',
        'cards' => [
            [
                'title' => 'Smart Executive',
                'items' => [
                    'Voice-controlled room assistant',
                    'Programmable mood lighting',
                    'Motorized blackout curtains',
                    'Digital privacy glass controls',
                ],
            ],
            [
                'title' => 'Comfort & Space',
                'items' => [
                    'Spacious king-size premium bed',
                    'Dedicated executive workspace',
                    'Separate private lounge',
                    'Floor-to-ceiling skyline windows',
                    'Premium in-room minibar',
                ],
            ],
            [
                'title' => 'Signature Design',
                'items' => [
                    'Charcoal stone walls',
                    'Walnut wood paneling',
                    'Brushed gold accents',
                    'Black marble furnishings',
                ],
            ],
        ],
    ],
    'Emperor Presidential' => [
        'category' => 'SIGNATURE ROOM',
        'heading' => 'EMPEROR PRESIDENTIAL',
        'tagline' => 'Where Absolute Luxury Becomes Personal',
        'cards' => [
            [
                'title' => 'Smart Future',
                'items' => [
                    'Full AI room orchestration hub',
                    'Biometric private suite access',
                    'Digital concierge display',
                    'Intelligent climate zoning',
                    'Personalized scene presets',
                    'Switchable smart glass walls',
                ],
            ],
            [
                'title' => 'Comfort & Space',
                'items' => [
                    'Master king-size premium bed',
                    'Private executive office',
                    'Exclusive lounge area',
                    'Private dining space',
                    'Designer walk-in wardrobe',
                    'Private signature bar',
                ],
            ],
            [
                'title' => 'Signature Design',
                'items' => [
                    'Double-height luxury ceiling',
                    'Panoramic skyline glass walls',
                    'Charcoal stone walls',
                    'Walnut wood paneling',
                    'Brushed gold accents',
                    'Black marble furnishings',
                ],
            ],
        ],
    ],
];

renderHeader('Suites & Rooms | Emperor Hotel', ['../assets/css/site/rooms.css'], 'rooms-showcase-page');
?>
<nav class="rooms-nav" aria-label="Primary navigation">
    <div class="rooms-nav__container">
        <a class="rooms-nav__logo" href="home.php" aria-label="Emperor Hotel home">
            <img src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel logo">
        </a>

        <div class="rooms-nav__links">
            <a class="rooms-nav__link" href="home.php">HOME</a>
            <a class="rooms-nav__link rooms-nav__link--active" href="rooms.php">SUITES & ROOM</a>
        </div>

        <div class="rooms-nav__auth">
            <?php foreach ($authLinks as $link): ?>
                <a class="rooms-nav__cta rooms-nav__cta--<?php echo e($link['variant']); ?>" href="<?php echo e($link['href']); ?>"><?php echo e($link['label']); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<main>
    <div class="rooms-flash">
        <?php renderFlashBlock(); ?>
    </div>

    <section class="rooms-hero">
        <img src="../assets/images/rooms/hero.jpg" alt="Luxury suite interior at Emperor Hotel">
        <div class="rooms-hero__content">
            <h1>EMPEROR'S HOTEL</h1>
            <p>SUITES & ROOMS SECTION</p>
            <a href="home.php">HOME</a>
        </div>
    </section>

    <?php if ($roomDataUnavailable): ?>
        <section class="rooms-notice">
            <p>Room pricing is temporarily unavailable. The gallery and room details are still available below.</p>
        </section>
    <?php endif; ?>

    <?php foreach ($catalog as $roomType => $roomInfo): ?>
        <?php
            $presentation = $roomPresentation[$roomType] ?? [
                'category' => strtoupper($roomType),
                'heading' => strtoupper($roomType),
                'tagline' => $roomInfo['tagline'],
                'cards' => [],
            ];
            $includedPerks = roomIncludedPerksText($roomType);
            $stats = $roomStats[$roomType] ?? [
                'available' => 0,
                'total' => 0,
                'lowest_price' => 0.0,
            ];
            $priceText = 'PRICE NOT SET';

            if ($roomDataUnavailable) {
                $priceText = 'PRICING UNAVAILABLE';
            } elseif ((float) $stats['lowest_price'] > 0) {
                $priceText = formatMoney((float) $stats['lowest_price']) . ' / NIGHT';
            }
        ?>
        <section class="rooms">
            <div class="container-carousel">
                <div class="carousel" data-carousel>
                    <button class="carousel-control prev" type="button" data-carousel-prev aria-label="Previous <?php echo e($roomType); ?> image">&#10094;</button>

                    <?php foreach ($roomInfo['carousel'] as $slideIndex => $imagePath): ?>
                        <div class="carousel-slide <?php echo $slideIndex === 0 ? 'is-active' : ''; ?>">
                            <img src="<?php echo e($imagePath); ?>" alt="<?php echo e($roomType . ' room view ' . ($slideIndex + 1)); ?>">
                        </div>
                    <?php endforeach; ?>

                    <button class="carousel-control next" type="button" data-carousel-next aria-label="Next <?php echo e($roomType); ?> image">&#10095;</button>

                    <div class="carousel-indicators">
                        <?php foreach ($roomInfo['carousel'] as $slideIndex => $_): ?>
                            <button
                                class="carousel-indicator <?php echo $slideIndex === 0 ? 'is-active' : ''; ?>"
                                type="button"
                                data-carousel-indicator="<?php echo e($slideIndex); ?>"
                                aria-label="Show <?php echo e($roomType); ?> image <?php echo e($slideIndex + 1); ?>"
                            ></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="container-content">
                <div class="content">
                    <h2><?php echo e($presentation['category']); ?></h2>
                    <h1><?php echo e($presentation['heading']); ?></h1>
                    <p><?php echo e($presentation['tagline']); ?></p>
                    <p class="room-inclusion-line">
                        Comes with: <?php echo e($includedPerks); ?>.
                    </p>
                </div>

                <div class="container-card">
                    <?php foreach ($presentation['cards'] as $card): ?>
                        <article class="card">
                            <div class="card-content">
                                <p><?php echo e($card['title']); ?></p>
                                <hr>
                                <ul>
                                    <?php foreach ($card['items'] as $item): ?>
                                        <li><?php echo e($item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="room-actions">
                    <a class="room-price room-price--booking" href="<?php echo e($reservationHref); ?>" aria-label="<?php echo e($reservationLabel . ' - ' . $roomType . ' from ' . $priceText); ?>">
                        <?php echo e($priceText); ?>
                    </a>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
</main>

<script>
    const carousels = Array.from(document.querySelectorAll("[data-carousel]"));

    carousels.forEach((carousel) => {
        const slides = Array.from(carousel.querySelectorAll(".carousel-slide"));
        const indicators = Array.from(carousel.querySelectorAll("[data-carousel-indicator]"));
        const previousButton = carousel.querySelector("[data-carousel-prev]");
        const nextButton = carousel.querySelector("[data-carousel-next]");
        let currentSlide = 0;
        let autoSlideId = null;

        const showSlide = (index) => {
            slides.forEach((slide, slideIndex) => {
                slide.classList.toggle("is-active", slideIndex === index);
            });

            indicators.forEach((indicator, indicatorIndex) => {
                indicator.classList.toggle("is-active", indicatorIndex === index);
            });

            currentSlide = index;
        };

        const moveSlide = (step) => {
            const nextSlide = (currentSlide + step + slides.length) % slides.length;
            showSlide(nextSlide);
        };

        const stopAutoSlide = () => {
            if (autoSlideId) {
                window.clearInterval(autoSlideId);
            }
        };

        const startAutoSlide = () => {
            stopAutoSlide();
            autoSlideId = window.setInterval(() => {
                moveSlide(1);
            }, 5000);
        };

        previousButton.addEventListener("click", () => {
            moveSlide(-1);
            startAutoSlide();
        });

        nextButton.addEventListener("click", () => {
            moveSlide(1);
            startAutoSlide();
        });

        indicators.forEach((indicator, index) => {
            indicator.addEventListener("click", () => {
                showSlide(index);
                startAutoSlide();
            });
        });

        carousel.addEventListener("mouseenter", stopAutoSlide);
        carousel.addEventListener("mouseleave", startAutoSlide);

        showSlide(0);
        startAutoSlide();
    });
</script>
</body>
</html>
