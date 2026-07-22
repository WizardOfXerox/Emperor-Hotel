<?php

declare(strict_types=1);

function groupedRoomsByType(array $rooms): array
{
    $groupedRooms = array_fill_keys(Room::types(), []);

    foreach ($rooms as $room) {
        $roomType = (string) ($room['room_type'] ?? '');

        if (!isset($groupedRooms[$roomType])) {
            $groupedRooms[$roomType] = [];
        }

        $groupedRooms[$roomType][] = $room;
    }

    return array_filter(
        $groupedRooms,
        static fn (array $roomsForType): bool => count($roomsForType) > 0
    );
}

function renderRoomChoiceCards(array $rooms, ?int $selectedRoomId = null, bool $allowSelectedUnavailable = false, ?PDO $db = null): void
{
    static $pickerCount = 0;

    $pickerCount++;
    $pickerId = 'roomPicker' . $pickerCount;
    $groupedRooms = groupedRoomsByType($rooms);

    if (!$groupedRooms) {
        echo '<div class="room-choice-empty">No room records are available yet.</div>';
        return;
    }

    $availableCount = 0;
    $unavailableCount = 0;

    foreach ($rooms as $room) {
        $isAvailable = array_key_exists('is_available_for_dates', $room)
            ? (bool) $room['is_available_for_dates']
            : (($room['status'] ?? '') === 'Available');

        if ($isAvailable) {
            $availableCount++;
        } else {
            $unavailableCount++;
        }
    }

    echo '<div class="room-picker" id="' . e($pickerId) . '" data-room-picker>';
    echo '<div class="room-picker__toolbar" aria-label="Room filters">';
    echo '<button class="room-filter-button active" type="button" data-room-filter="all">All <span data-room-filter-count="all">' . e(count($rooms)) . '</span></button>';
    echo '<button class="room-filter-button" type="button" data-room-filter="available">Available <span data-room-filter-count="available">' . e($availableCount) . '</span></button>';
    echo '<button class="room-filter-button" type="button" data-room-filter="unavailable">Unavailable <span data-room-filter-count="unavailable">' . e($unavailableCount) . '</span></button>';
    echo '</div>';
    echo '<div class="room-picker__viewport">';
    echo '<div class="room-choice-categories">';

    foreach ($groupedRooms as $roomType => $roomsForType) {
        echo '<section class="room-choice-category" data-room-category>';
        echo '<div class="room-choice-category__head">';
        echo '<h4>' . e($roomType) . '</h4>';
        echo '<span>' . e(count($roomsForType)) . ' rooms</span>';
        echo '</div>';
        echo '<div class="room-choice-grid">';

        foreach ($roomsForType as $room) {
            $roomId = (int) $room['room_id'];
            $isSelected = $selectedRoomId === $roomId;
            $status = (string) $room['status'];
            $displayStatus = (string) ($room['availability_label'] ?? $status);
            $includedPerks = function_exists('roomIncludedPerksText') ? roomIncludedPerksText($roomType) : 'Standard room amenities';
            $isAvailable = array_key_exists('is_available_for_dates', $room)
                ? (bool) $room['is_available_for_dates']
                : $status === 'Available';
            $isSelectable = $isAvailable || ($allowSelectedUnavailable && $isSelected);
            $inputId = 'room_choice_' . $roomId;
            $cardClass = 'room-choice-card';
            $statusClass = $isAvailable ? 'is-available' : 'is-unavailable';

            if ($isSelected) {
                $cardClass .= ' is-selected';
            }

            if (!$isSelectable) {
                $cardClass .= ' is-disabled';
            }

            echo '<label class="' . e($cardClass) . '" for="' . e($inputId) . '" data-room-card data-room-id="' . e($roomId) . '" data-room-availability="' . e($isAvailable ? 'available' : 'unavailable') . '" data-room-number="' . e($room['room_number']) . '" data-room-type="' . e($roomType) . '" data-room-price="' . e((float) $room['price_per_night']) . '" data-room-included-perks="' . e($includedPerks) . '">';
            echo '<input class="room-choice-input" type="radio" id="' . e($inputId) . '" name="room_id" value="' . e($roomId) . '"' . ($isSelected ? ' checked' : '') . (!$isSelectable ? ' disabled' : '') . '>';
            echo '<span class="room-choice-card__top">';
            echo '<span class="room-status-dot ' . e($statusClass) . '" data-room-status-dot aria-hidden="true"></span>';
            echo '<span class="room-choice-card__status" data-room-status-label>' . e($displayStatus) . '</span>';
            echo '</span>';
            echo '<span class="room-choice-card__room-number">Room ' . e($room['room_number']) . '</span>';
            echo '<strong>' . e($roomType) . '</strong>';
            $maxCapacity = (int) ($room['max_capacity'] ?? ($roomType === 'Emperor Presidential' ? 6 : ($roomType === 'Royal Executive' ? 4 : 2)));
            echo '<small>' . e(formatMoney((float) $room['price_per_night'])) . ' / night</small>';
            echo '<small><i class="bi bi-people me-1"></i>Max ' . e($maxCapacity) . ' Guests</small>';
            echo '</label>';
        }

        echo '</div>';
        echo '</section>';
    }

    echo '</div>';
    echo '</div>';
    echo '<div class="room-picker__empty" hidden>No rooms match this filter.</div>';
    echo '</div>';
    echo '<script>
document.querySelectorAll("[data-room-picker]").forEach((picker) => {
    if (picker.dataset.roomPickerReady === "true") {
        return;
    }

    picker.dataset.roomPickerReady = "true";
    const cards = Array.from(picker.querySelectorAll("[data-room-card]"));
    const filterButtons = Array.from(picker.querySelectorAll("[data-room-filter]"));
    const emptyMessage = picker.querySelector(".room-picker__empty");
    let activeFilter = "all";

    function refreshSelectedCard() {
        let selectedCard = null;
        const form = picker.closest("form");
        const inclusionsPreview = form ? form.querySelector("[data-room-inclusions-preview]") : null;

        cards.forEach((card) => {
            const input = card.querySelector(".room-choice-input");
            const isSelected = Boolean(input && input.checked);
            card.classList.toggle("is-selected", isSelected);

            if (isSelected) {
                selectedCard = card;
            }
        });

        if (inclusionsPreview) {
            if (!selectedCard) {
                inclusionsPreview.querySelector("[data-room-inclusions-label]").textContent = "Select a room";
                inclusionsPreview.querySelector("[data-room-inclusions-name]").textContent = "Room inclusions will appear here.";
                inclusionsPreview.querySelector("[data-room-inclusions-list]").textContent = "Choose a room to see what comes with it.";
                return;
            }

            inclusionsPreview.querySelector("[data-room-inclusions-label]").textContent = `Room ${selectedCard.dataset.roomNumber} - ${selectedCard.dataset.roomType}`;
            inclusionsPreview.querySelector("[data-room-inclusions-name]").textContent = "Included with this room type";
            inclusionsPreview.querySelector("[data-room-inclusions-list]").textContent = selectedCard.dataset.roomIncludedPerks || "Standard room amenities";
        }
    }

    function refreshFilterCounts() {
        const counts = cards.reduce((totals, card) => {
            totals.all += 1;
            totals[card.dataset.roomAvailability === "available" ? "available" : "unavailable"] += 1;

            return totals;
        }, { all: 0, available: 0, unavailable: 0 });

        Object.entries(counts).forEach(([filter, count]) => {
            const countElement = picker.querySelector(`[data-room-filter-count="${filter}"]`);

            if (countElement) {
                countElement.textContent = String(count);
            }
        });
    }

    function applyFilter(filter) {
        activeFilter = filter;
        let visibleCount = 0;

        cards.forEach((card) => {
            const showCard = filter === "all" || card.dataset.roomAvailability === filter;
            card.hidden = !showCard;

            if (showCard) {
                visibleCount++;
            }
        });

        picker.querySelectorAll("[data-room-category]").forEach((category) => {
            const hasVisibleCards = Array.from(category.querySelectorAll("[data-room-card]")).some((card) => !card.hidden);
            category.hidden = !hasVisibleCards;
        });

        if (emptyMessage) {
            emptyMessage.hidden = visibleCount > 0;
        }
    }

    function updateCardAvailability(card, available, label) {
        const input = card.querySelector(".room-choice-input");
        const statusDot = card.querySelector("[data-room-status-dot]");
        const statusLabel = card.querySelector("[data-room-status-label]");

        card.dataset.roomAvailability = available ? "available" : "unavailable";
        card.classList.toggle("is-disabled", !available);

        if (input) {
            if (!available && input.checked) {
                input.checked = false;
                input.dispatchEvent(new Event("change", { bubbles: true }));
            }

            input.disabled = !available;
        }

        if (statusDot) {
            statusDot.classList.toggle("is-available", available);
            statusDot.classList.toggle("is-unavailable", !available);
        }

        if (statusLabel) {
            statusLabel.textContent = label || (available ? "Available for dates" : "Unavailable for dates");
        }
    }

    picker.roomPickerApplyAvailability = (rooms) => {
        const availabilityByRoom = new Map(
            rooms.map((room) => [String(room.room_id), room])
        );

        cards.forEach((card) => {
            const room = availabilityByRoom.get(String(card.dataset.roomId));

            if (!room) {
                return;
            }

            updateCardAvailability(card, Boolean(room.available), room.label || "");
        });

        refreshFilterCounts();
        refreshSelectedCard();
        applyFilter(activeFilter);
    };

    cards.forEach((card) => {
        card.addEventListener("click", () => {
            const input = card.querySelector(".room-choice-input");

            if (!input || input.disabled) {
                return;
            }

            input.checked = true;
            input.dispatchEvent(new Event("change", { bubbles: true }));
        });
    });

    picker.addEventListener("change", (event) => {
        if (event.target && event.target.classList.contains("room-choice-input")) {
            refreshSelectedCard();
        }
    });

    filterButtons.forEach((button) => {
        button.addEventListener("click", () => {
            filterButtons.forEach((item) => item.classList.remove("active"));
            button.classList.add("active");
            applyFilter(button.dataset.roomFilter || "all");
        });
    });

    refreshFilterCounts();
    refreshSelectedCard();
    applyFilter("all");
});
</script>';
}

function renderRoomInclusionPreview(?string $selectedRoomType = null): void
{
    $includedPerks = $selectedRoomType && function_exists('roomIncludedPerksText')
        ? roomIncludedPerksText($selectedRoomType)
        : null;

    echo '<div class="room-inclusion-preview" data-room-inclusions-preview>';
    echo '<p class="eyebrow mb-1" data-room-inclusions-label>' . e($selectedRoomType ? $selectedRoomType : 'Select a room') . '</p>';
    echo '<h4 data-room-inclusions-name>' . e($selectedRoomType ? 'Included with this room type' : 'Room inclusions will appear here.') . '</h4>';
    echo '<p class="muted-copy mb-0">Comes with: <span data-room-inclusions-list>' . e($includedPerks ?? 'Choose a room to see what comes with it.') . '</span></p>';
    echo '</div>';
}

function renderReservationCostTracker(): void
{
    echo '<div class="cost-tracker" data-cost-tracker>';
    echo '<div class="cost-tracker__head">';
    echo '<div>';
    echo '<p class="eyebrow mb-1">Cost Tracker</p>';
    echo '<h4 class="mb-0">Estimated Reservation Cost</h4>';
    echo '</div>';
    echo '<span class="badge-soft" data-cost-nights>0 nights</span>';
    echo '</div>';
    echo '<div class="cost-tracker__grid">';
    echo '<div><span>Selected room</span><strong data-cost-room>Choose a room</strong></div>';
    echo '<div><span>Nightly rate</span><strong data-cost-rate>PHP 0.00</strong></div>';
    echo '<div><span>Room subtotal</span><strong data-cost-subtotal>PHP 0.00</strong></div>';
    echo '<div><span>Room inclusions</span><strong data-cost-included>Choose a room</strong></div>';
    echo '<div class="cost-tracker__total"><span>Estimated total</span><strong data-cost-total>PHP 0.00</strong></div>';
    echo '</div>';
    echo '<p class="muted-copy small mb-0" data-cost-note>Select a room and valid check-in/check-out dates to calculate the estimated total.</p>';
    echo '</div>';
    echo <<<'HTML'
<script>
document.querySelectorAll("[data-cost-tracker]").forEach((tracker) => {
    if (tracker.dataset.costTrackerReady === "true") {
        return;
    }

    tracker.dataset.costTrackerReady = "true";
    const form = tracker.closest("form");

    if (!form) {
        return;
    }

    const money = (amount) => `PHP ${Number(amount || 0).toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })}`;

    const text = (selector, value) => {
        const element = tracker.querySelector(selector);

        if (element) {
            element.textContent = value;
        }
    };

    const calculateNights = () => {
        const checkInInput = form.querySelector('input[name="check_in"]');
        const checkOutInput = form.querySelector('input[name="check_out"]');
        const checkIn = checkInInput ? checkInInput.value : "";
        const checkOut = checkOutInput ? checkOutInput.value : "";

        if (!checkIn || !checkOut) {
            return 0;
        }

        const start = new Date(`${checkIn}T00:00:00`);
        const end = new Date(`${checkOut}T00:00:00`);
        const diff = Math.floor((end - start) / 86400000);

        return diff > 0 ? diff : 0;
    };

    const update = () => {
        const selectedInput = form.querySelector(".room-choice-input:checked");
        const selectedCard = selectedInput ? selectedInput.closest("[data-room-card]") : null;
        const nights = calculateNights();
        const price = selectedCard ? Number(selectedCard.dataset.roomPrice || 0) : 0;
        const subtotal = price * nights;
        const total = subtotal;

        text("[data-cost-room]", selectedCard ? `Room ${selectedCard.dataset.roomNumber} - ${selectedCard.dataset.roomType}` : "Choose a room");
        text("[data-cost-rate]", money(price));
        text("[data-cost-nights]", `${nights} ${nights === 1 ? "night" : "nights"}`);
        text("[data-cost-subtotal]", money(subtotal));
        text("[data-cost-included]", selectedCard ? selectedCard.dataset.roomIncludedPerks || "Standard room amenities" : "Choose a room");
        text("[data-cost-total]", money(total));

        const note = tracker.querySelector("[data-cost-note]");

        if (note) {
            if (!selectedCard) {
                note.textContent = "Choose a room to start the estimate.";
            } else if (nights === 0) {
                note.textContent = "Select valid check-in and check-out dates to calculate nights.";
            } else {
                note.textContent = "Estimate is based on the selected room rate and number of nights.";
            }
        }
    };

    form.addEventListener("change", update);
    form.addEventListener("input", update);
    update();
});
</script>
HTML;
}

function renderRoomAvailabilityUpdater(): void
{
    echo <<<'HTML'
<script>
document.querySelectorAll("[data-dynamic-room-availability]").forEach((form) => {
    if (form.dataset.dynamicAvailabilityReady === "true") {
        return;
    }

    form.dataset.dynamicAvailabilityReady = "true";

    const checkInInput = form.querySelector('input[name="check_in"]');
    const checkOutInput = form.querySelector('input[name="check_out"]');
    const picker = form.querySelector("[data-room-picker]");
    const note = form.querySelector("[data-room-availability-note]");
    const availabilityUrl = form.dataset.availabilityUrl || "";
    let requestCounter = 0;
    let debounceTimer = null;

    const setNote = (message, isWarning = false) => {
        if (!note) {
            return;
        }

        note.textContent = message;
        note.classList.toggle("text-warning", isWarning);
    };

    const refreshAvailability = () => {
        if (!checkInInput || !checkOutInput || !picker || !availabilityUrl) {
            return;
        }

        const checkIn = checkInInput.value;
        const checkOut = checkOutInput.value;

        if (!checkIn || !checkOut) {
            setNote("Room availability updates automatically once both stay dates are selected.");
            return;
        }

        const start = new Date(`${checkIn}T00:00:00`);
        const end = new Date(`${checkOut}T00:00:00`);

        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) {
            setNote("Check-out date must be after the check-in date before availability can update.", true);
            return;
        }

        const params = new URLSearchParams({
            check_in: checkIn,
            check_out: checkOut
        });
        const reservationIdInput = form.querySelector('input[name="reservation_id"]');

        if (reservationIdInput && reservationIdInput.value) {
            params.set("exclude_reservation_id", reservationIdInput.value);
        }

        const currentRequest = ++requestCounter;
        setNote("Checking room availability for the selected dates...");

        fetch(`${availabilityUrl}?${params.toString()}`, {
            headers: {
                "Accept": "application/json"
            }
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error("Unable to check room availability.");
                }

                return response.json();
            })
            .then((payload) => {
                if (currentRequest !== requestCounter) {
                    return;
                }

                if (!payload.ok) {
                    setNote(payload.message || "Choose valid dates to check room availability.", true);
                    return;
                }

                if (typeof picker.roomPickerApplyAvailability === "function") {
                    picker.roomPickerApplyAvailability(payload.rooms || []);
                }

                setNote("Room cards now reflect availability for the selected check-in and check-out dates.");
            })
            .catch(() => {
                if (currentRequest === requestCounter) {
                    setNote("Room availability could not be refreshed. Please try again.", true);
                }
            });
    };

    const scheduleRefresh = () => {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(refreshAvailability, 250);
    };

    checkInInput?.addEventListener("change", scheduleRefresh);
    checkOutInput?.addEventListener("change", scheduleRefresh);
    checkInInput?.addEventListener("input", scheduleRefresh);
    checkOutInput?.addEventListener("input", scheduleRefresh);
    refreshAvailability();
});
</script>
HTML;
}
