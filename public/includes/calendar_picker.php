<?php

declare(strict_types=1);

/**
 * Renders the Airbnb/Booking-style Interactive Date Range Calendar Picker
 */
function renderCalendarPickerModal(string $checkInVal = '', string $checkOutVal = ''): void
{
    $today = new DateTimeImmutable('today');
    $checkIn = $checkInVal ?: $today->format('Y-m-d');
    $checkOut = $checkOutVal ?: $today->modify('+1 day')->format('Y-m-d');
?>
<!-- Calendar Modal -->
<div class="modal fade" id="calendarPickerModal" tabindex="-1" aria-labelledby="calendarPickerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow-lg border-gold-glow bg-dark text-light">
      <div class="modal-header border-secondary px-4 py-3">
        <h5 class="modal-title font-serif text-gold fw-bold" id="calendarPickerModalLabel">
            <i class="bi bi-calendar-range me-2"></i>Select Stay Dates
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        
        <!-- Mode Tabs -->
        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary">
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-warning active" id="btnExactDates">Calendar</button>
                <button type="button" class="btn btn-outline-warning" id="btnFlexibleDates">I'm flexible</button>
            </div>
            <div id="stayDurationBadge" class="badge bg-gold text-dark fs-6 px-3 py-2 fw-bold rounded-pill">
                Jul 22 - Jul 25 (3-night stay)
            </div>
        </div>

        <!-- Date Inputs Sync -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label text-xs text-uppercase tracking-wider text-muted">Check-In Date</label>
                <input type="date" id="modalCheckInInput" class="form-control form-control-dark border-gold bg-dark text-light fw-bold" value="<?= e($checkIn) ?>" min="<?= $today->format('Y-m-d') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label text-xs text-uppercase tracking-wider text-muted">Check-Out Date</label>
                <input type="date" id="modalCheckOutInput" class="form-control form-control-dark border-gold bg-dark text-light fw-bold" value="<?= e($checkOut) ?>" min="<?= $today->modify('+1 day')->format('Y-m-d') ?>">
            </div>
        </div>

        <!-- Flexible Date Options Chips -->
        <div class="mb-4">
            <label class="form-label text-xs text-uppercase tracking-wider text-muted d-block mb-2">Flexible Date Options</label>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 active flex-chip" onclick="setFlexRange(0)">Exact dates</button>
                <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 flex-chip" onclick="setFlexRange(1)">± 1 day</button>
                <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 flex-chip" onclick="setFlexRange(2)">± 2 days</button>
                <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 flex-chip" onclick="setFlexRange(3)">± 3 days</button>
            </div>
        </div>

        <!-- Visual Month Calendar Grid -->
        <div id="calendarVisualGrid" class="p-3 bg-black bg-opacity-40 rounded-3 border border-secondary">
            <!-- Dynamic Month Header -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <button type="button" class="btn btn-sm btn-outline-secondary text-light" onclick="shiftCalendarMonth(-1)"><i class="bi bi-chevron-left"></i></button>
                <h6 class="m-0 font-serif text-gold fw-bold" id="calendarMonthTitle">July 2026</h6>
                <button type="button" class="btn btn-sm btn-outline-secondary text-light" onclick="shiftCalendarMonth(1)"><i class="bi bi-chevron-right"></i></button>
            </div>
            
            <!-- Weekday Labels -->
            <div class="row row-cols-7 g-1 text-center text-xs text-muted mb-2 fw-bold">
                <div class="col">Sun</div>
                <div class="col">Mon</div>
                <div class="col">Tue</div>
                <div class="col">Wed</div>
                <div class="col">Thu</div>
                <div class="col">Fri</div>
                <div class="col">Sat</div>
            </div>
            <!-- Days Grid -->
            <div class="row row-cols-7 g-1 text-center" id="calendarDaysGrid"></div>
        </div>

      </div>
      <div class="modal-footer border-secondary px-4 py-3">
        <button type="button" class="btn btn-outline-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-gold rounded-pill px-5 fw-bold" onclick="applySelectedDatesFromModal()">Done</button>
      </div>
    </div>
  </div>
</div>

<script>
let calCurrentYear = 2026;
let calCurrentMonth = 6; // 0-indexed: 6 = July

document.addEventListener('DOMContentLoaded', () => {
    const checkInVal = document.getElementById('modalCheckInInput')?.value;
    if (checkInVal) {
        const d = new Date(checkInVal);
        if (!isNaN(d)) {
            calCurrentYear = d.getFullYear();
            calCurrentMonth = d.getMonth();
        }
    }
    renderVisualCalendarGrid();
    updateStayDurationBadge();

    document.getElementById('modalCheckInInput')?.addEventListener('change', () => {
        updateStayDurationBadge();
        renderVisualCalendarGrid();
    });
    document.getElementById('modalCheckOutInput')?.addEventListener('change', () => {
        updateStayDurationBadge();
        renderVisualCalendarGrid();
    });
});

function renderVisualCalendarGrid() {
    const monthTitle = document.getElementById('calendarMonthTitle');
    const daysGrid = document.getElementById('calendarDaysGrid');
    if (!daysGrid) return;

    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    monthTitle.textContent = `${monthNames[calCurrentMonth]} ${calCurrentYear}`;

    const firstDay = new Date(calCurrentYear, calCurrentMonth, 1).getDay();
    const totalDays = new Date(calCurrentYear, calCurrentMonth + 1, 0).getDate();

    const checkInDate = new Date(document.getElementById('modalCheckInInput').value);
    const checkOutDate = new Date(document.getElementById('modalCheckOutInput').value);

    let html = '';
    for (let i = 0; i < firstDay; i++) {
        html += `<div class="col p-2 text-muted opacity-25"></div>`;
    }

    for (let day = 1; day <= totalDays; day++) {
        const cellDate = new Date(calCurrentYear, calCurrentMonth, day);
        const yyyymmdd = `${calCurrentYear}-${String(calCurrentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        
        let cellClass = 'btn-outline-secondary text-light';
        if (cellDate.getTime() === checkInDate.getTime() || cellDate.getTime() === checkOutDate.getTime()) {
            cellClass = 'btn-primary text-white fw-bold shadow';
        } else if (cellDate > checkInDate && cellDate < checkOutDate) {
            cellClass = 'bg-primary bg-opacity-25 text-gold fw-bold';
        }

        html += `
            <div class="col p-1">
                <button type="button" class="btn btn-sm w-100 p-2 rounded ${cellClass}" onclick="selectCalendarCellDate('${yyyymmdd}')">
                    ${day}
                </button>
            </div>
        `;
    }

    daysGrid.innerHTML = html;
}

function selectCalendarCellDate(dateStr) {
    const checkInInput = document.getElementById('modalCheckInInput');
    const checkOutInput = document.getElementById('modalCheckOutInput');
    const checkIn = new Date(checkInInput.value);
    const checkOut = new Date(checkOutInput.value);
    const selected = new Date(dateStr);

    if (!checkInInput.dataset.selectingState || checkInInput.dataset.selectingState === 'end') {
        checkInInput.value = dateStr;
        checkInInput.dataset.selectingState = 'start';
        // Auto set checkOut to +1 day
        const nextDay = new Date(selected);
        nextDay.setDate(nextDay.getDate() + 1);
        checkOutInput.value = nextDay.toISOString().split('T')[0];
    } else {
        if (selected <= checkIn) {
            checkInInput.value = dateStr;
            const nextDay = new Date(selected);
            nextDay.setDate(nextDay.getDate() + 1);
            checkOutInput.value = nextDay.toISOString().split('T')[0];
        } else {
            checkOutInput.value = dateStr;
            checkInInput.dataset.selectingState = 'end';
        }
    }

    updateStayDurationBadge();
    renderVisualCalendarGrid();
}

function shiftCalendarMonth(delta) {
    calCurrentMonth += delta;
    if (calCurrentMonth > 11) {
        calCurrentMonth = 0;
        calCurrentYear++;
    } else if (calCurrentMonth < 0) {
        calCurrentMonth = 11;
        calCurrentYear--;
    }
    renderVisualCalendarGrid();
}

function updateStayDurationBadge() {
    const checkInVal = document.getElementById('modalCheckInInput')?.value;
    const checkOutVal = document.getElementById('modalCheckOutInput')?.value;
    const badge = document.getElementById('stayDurationBadge');

    if (!checkInVal || !checkOutVal || !badge) return;

    const inD = new Date(checkInVal);
    const outD = new Date(checkOutVal);

    if (isNaN(inD) || isNaN(outD) || outD <= inD) {
        badge.textContent = "Invalid dates";
        return;
    }

    const nights = Math.round((outD - inD) / (1000 * 60 * 60 * 24));
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const text = `${monthNames[inD.getMonth()]} ${inD.getDate()} - ${monthNames[outD.getMonth()]} ${outD.getDate()} (${nights}-night stay)`;
    badge.textContent = text;
}

function setFlexRange(days) {
    document.querySelectorAll('.flex-chip').forEach(el => el.classList.remove('active', 'btn-gold'));
    event.target.classList.add('active', 'btn-gold');
}

function applySelectedDatesFromModal() {
    const checkIn = document.getElementById('modalCheckInInput').value;
    const checkOut = document.getElementById('modalCheckOutInput').value;

    const mainCheckIn = document.querySelector('input[name="check_in"]');
    const mainCheckOut = document.querySelector('input[name="check_out"]');

    if (mainCheckIn) mainCheckIn.value = checkIn;
    if (mainCheckOut) mainCheckOut.value = checkOut;

    const modalEl = document.getElementById('calendarPickerModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    // Submit search form if available
    const searchForm = document.getElementById('availabilitySearchForm') || document.getElementById('bookingForm');
    if (searchForm) {
        searchForm.submit();
    }
}
</script>
<?php
}
