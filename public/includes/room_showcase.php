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
    echo '<div class="d-flex flex-column flex-lg-row justify-content-center align-items-center gap-3 mb-4">';
    echo '<div>';
    echo '<p class="mb-2 text-center eyebrow">Suites & Rooms</p>';
    echo '<h2 class="section-title mb-0">Explore room types and live availability</h2>';
    echo '</div>';
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

        $availCount = (int) ($stats['available'] ?? 0);
        $totalCount = (int) ($stats['total'] ?? 0);
        
        $statusBadgeHtml = '';
        if ($availCount > 2) {
            $statusBadgeHtml = '<span class="badge bg-success px-3 py-2 rounded-pill fs-6 fw-bold mb-2 me-2"><i class="bi bi-circle-fill me-1"></i>🟢 ' . $availCount . ' Available Now</span>';
        } elseif ($availCount > 0) {
            $statusBadgeHtml = '<span class="badge bg-warning text-dark px-3 py-2 rounded-pill fs-6 fw-bold mb-2 me-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>🟡 Only ' . $availCount . ' Left!</span>';
        } else {
            $statusBadgeHtml = '<span class="badge bg-danger px-3 py-2 rounded-pill fs-6 fw-bold mb-2 me-2"><i class="bi bi-x-circle-fill me-1"></i>🔴 Fully Booked</span>';
        }

        echo '<div class="container-content">';
        echo '<div class="content">';
        echo '<div>' . $statusBadgeHtml . '<span class="badge bg-dark border border-gold text-gold px-3 py-2 rounded-pill fs-6 fw-bold">' . e($roomInfo['dimensions'] ?? '38 sqm') . '</span></div>';
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

        echo '<div class="room-actions d-flex flex-wrap gap-2 align-items-center">';
        echo '<a class="room-price room-price--booking" href="' . e($reservationHref) . '" aria-label="' . e($reservationLabel . ' - ' . $roomType . ' from ' . $priceText) . '">' . e($priceText) . '</a>';
        echo '<a class="btn btn-outline-warning rounded-pill px-4 py-2 fw-bold font-serif" href="room-detail.php?id=1"><i class="bi bi-eye-fill me-1"></i>Inspect Suite Details & Reviews</a>';
        echo '</div>';
        echo '</div></section>';
    }

    echo '</div></section>';

    echo '<script>
    (function() {
        function initCarousels() {
            const carousels = Array.from(document.querySelectorAll("[data-carousel]"));
            carousels.forEach((carousel) => {
                if (carousel.dataset.carouselInitialized) return;
                carousel.dataset.carouselInitialized = "true";

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
                    if (autoSlideId) window.clearInterval(autoSlideId);
                };

                const startAutoSlide = () => {
                    stopAutoSlide();
                    autoSlideId = window.setInterval(() => moveSlide(1), 4000);
                };

                if (previousButton) {
                    previousButton.addEventListener("click", (e) => {
                        e.preventDefault();
                        moveSlide(-1);
                        startAutoSlide();
                    });
                }

                if (nextButton) {
                    nextButton.addEventListener("click", (e) => {
                        e.preventDefault();
                        moveSlide(1);
                        startAutoSlide();
                    });
                }

                indicators.forEach((indicator, index) => {
                    indicator.addEventListener("click", (e) => {
                        e.preventDefault();
                        showSlide(index);
                        startAutoSlide();
                    });
                });

                carousel.style.cursor = 'grab';

                // Mouse Drag Events
                let startX = 0;
                let isDragging = false;

                carousel.addEventListener('mousedown', (e) => {
                    isDragging = true;
                    startX = e.clientX;
                    carousel.style.cursor = 'grabbing';
                    stopAutoSlide();
                });

                carousel.addEventListener('mouseup', (e) => {
                    if (!isDragging) return;
                    isDragging = false;
                    carousel.style.cursor = 'grab';
                    const diffX = e.clientX - startX;
                    if (Math.abs(diffX) > 30) {
                        if (diffX < 0) {
                            moveSlide(1);
                        } else {
                            moveSlide(-1);
                        }
                    }
                    startAutoSlide();
                });

                carousel.addEventListener('mouseleave', () => {
                    if (isDragging) {
                        isDragging = false;
                        carousel.style.cursor = 'grab';
                        startAutoSlide();
                    }
                });

                // Touch Swipe Events
                carousel.addEventListener('touchstart', (e) => {
                    startX = e.touches[0].clientX;
                    stopAutoSlide();
                }, { passive: true });

                carousel.addEventListener('touchend', (e) => {
                    const endX = e.changedTouches[0].clientX;
                    const diffX = endX - startX;
                    if (Math.abs(diffX) > 30) {
                        if (diffX < 0) {
                            moveSlide(1);
                        } else {
                            moveSlide(-1);
                        }
                    }
                    startAutoSlide();
                }, { passive: true });

                showSlide(0);
                startAutoSlide();
            });
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", initCarousels);
        } else {
            initCarousels();
        }
    })();
    </script>';
}
