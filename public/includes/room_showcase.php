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
        echo '<div class="carousel-wrapper position-relative">';
        echo '<button class="carousel-control prev" type="button" data-carousel-prev aria-label="Previous ' . e($roomType) . ' image">&#10094;</button>';
        echo '<div class="carousel-track" data-carousel>';

        foreach ($roomInfo['carousel'] as $slideIndex => $imagePath) {
            echo '<div class="carousel-slide">';
            echo '<img src="' . e($imagePath) . '" alt="' . e($roomType . ' room view ' . ($slideIndex + 1)) . '" draggable="false">';
            echo '</div>';
        }

        echo '</div>';
        echo '<button class="carousel-control next" type="button" data-carousel-next aria-label="Next ' . e($roomType) . ' image">&#10095;</button>';
        echo '</div></div>';

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

        $defaultFirstRoomIdMap = [
            'Imperial Deluxe' => 1,
            'Royal Executive' => 13,
            'Emperor Presidential' => 25,
        ];
        $inspectRoomId = $defaultFirstRoomIdMap[$roomType] ?? 1;

        if ($db) {
            try {
                $stmt = $db->prepare("SELECT room_id FROM rooms WHERE room_type = :type ORDER BY room_number ASC LIMIT 1");
                $stmt->execute(['type' => $roomType]);
                $foundRoomId = (int) $stmt->fetchColumn();
                if ($foundRoomId > 0) {
                    $inspectRoomId = $foundRoomId;
                }
            } catch (Throwable $e) {
                // Fallback to default first room ID map
            }
        }

        echo '<div class="room-actions d-flex flex-wrap gap-2 align-items-center">';
        echo '<a class="room-price room-price--booking" href="' . e($reservationHref) . '" aria-label="' . e($reservationLabel . ' - ' . $roomType . ' from ' . $priceText) . '">' . e($priceText) . '</a>';
        echo '<a class="btn btn-outline-warning rounded-pill px-4 py-2 fw-bold font-serif" href="room-detail.php?id=' . $inspectRoomId . '"><i class="bi bi-eye-fill me-1"></i>Inspect Suite Details & Reviews</a>';
        echo '</div>';
        echo '</div></section>';
    }

    echo '</div></section>';

    echo '<script>
    (function() {
        function initInfiniteCarousels() {
            const carouselWrappers = Array.from(document.querySelectorAll(".carousel-wrapper"));
            
            carouselWrappers.forEach((wrapper) => {
                if (wrapper.dataset.carouselInit) return;
                wrapper.dataset.carouselInit = "true";

                const track = wrapper.querySelector("[data-carousel]");
                const prevBtn = wrapper.querySelector("[data-carousel-prev]");
                const nextBtn = wrapper.querySelector("[data-carousel-next]");
                const slides = track ? Array.from(track.querySelectorAll(".carousel-slide")) : [];

                if (!track || slides.length === 0) return;

                let currentIndex = 0;
                let autoSlideTimer = null;
                let isDown = false;
                let startX = 0;
                let scrollLeft = 0;

                const totalSlides = slides.length;

                const scrollToSlide = (index, smooth = true) => {
                    currentIndex = (index + totalSlides) % totalSlides;
                    const slideWidth = track.clientWidth;
                    track.scrollTo({
                        left: currentIndex * slideWidth,
                        behavior: smooth ? "smooth" : "auto"
                    });
                };

                const nextSlide = () => {
                    scrollToSlide(currentIndex + 1);
                };

                const prevSlide = () => {
                    scrollToSlide(currentIndex - 1);
                };

                const startAutoSlide = () => {
                    stopAutoSlide();
                    autoSlideTimer = setInterval(() => {
                        nextSlide();
                    }, 4000);
                };

                const stopAutoSlide = () => {
                    if (autoSlideTimer) clearInterval(autoSlideTimer);
                };

                if (prevBtn) {
                    prevBtn.addEventListener("click", (e) => {
                        e.preventDefault();
                        prevSlide();
                        startAutoSlide();
                    });
                }

                if (nextBtn) {
                    nextBtn.addEventListener("click", (e) => {
                        e.preventDefault();
                        nextSlide();
                        startAutoSlide();
                    });
                }

                track.addEventListener("mousedown", (e) => {
                    isDown = true;
                    track.classList.add("is-dragging");
                    startX = e.pageX - track.offsetLeft;
                    scrollLeft = track.scrollLeft;
                    stopAutoSlide();
                });

                track.addEventListener("mouseleave", () => {
                    if (isDown) {
                        isDown = false;
                        track.classList.remove("is-dragging");
                        startAutoSlide();
                    }
                });

                track.addEventListener("mouseup", (e) => {
                    if (!isDown) return;
                    isDown = false;
                    track.classList.remove("is-dragging");
                    const diffX = (e.pageX - track.offsetLeft) - startX;
                    if (Math.abs(diffX) > 40) {
                        if (diffX < 0) {
                            nextSlide();
                        } else {
                            prevSlide();
                        }
                    } else {
                        scrollToSlide(currentIndex);
                    }
                    startAutoSlide();
                });

                track.addEventListener("mousemove", (e) => {
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - track.offsetLeft;
                    const walk = (x - startX) * 1.5;
                    track.scrollLeft = scrollLeft - walk;
                });

                track.addEventListener("touchstart", (e) => {
                    startX = e.touches[0].clientX;
                    stopAutoSlide();
                }, { passive: true });

                track.addEventListener("touchend", (e) => {
                    const endX = e.changedTouches[0].clientX;
                    const diffX = endX - startX;
                    if (Math.abs(diffX) > 40) {
                        if (diffX < 0) {
                            nextSlide();
                        } else {
                            prevSlide();
                        }
                    }
                    startAutoSlide();
                }, { passive: true });

                wrapper.addEventListener("mouseenter", stopAutoSlide);
                wrapper.addEventListener("mouseleave", startAutoSlide);

                startAutoSlide();
            });
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", initInfiniteCarousels);
        } else {
            initInfiniteCarousels();
        }
    })();
    </script>';
}
