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

function initCalendarApp() {
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
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCalendarApp);
} else {
    initCalendarApp();
}

setTimeout(initCalendarApp, 100);

function renderVisualCalendarGrid() {
    const monthTitle = document.getElementById('calendarMonthTitle');
    const daysGrid = document.getElementById('calendarDaysGrid');
    if (!daysGrid) return;

    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    monthTitle.textContent = `${monthNames[calCurrentMonth]} ${calCurrentYear}`;

    const firstDay = new Date(calCurrentYear, calCurrentMonth, 1).getDay();
    const totalDays = new Date(calCurrentYear, calCurrentMonth + 1, 0).getDate();

    const checkInInputVal = document.getElementById('modalCheckInInput')?.value;
    const checkOutInputVal = document.getElementById('modalCheckOutInput')?.value;
    const checkInDate = checkInInputVal ? new Date(checkInInputVal) : null;
    const checkOutDate = checkOutInputVal ? new Date(checkOutInputVal) : null;
    const today = new Date();
    today.setHours(0,0,0,0);

    let html = '';
    // Padding empty cells before the 1st of the month
    for (let i = 0; i < firstDay; i++) {
        html += `<div class="calendar-day-empty"></div>`;
    }

    for (let day = 1; day <= totalDays; day++) {
        const cellDate = new Date(calCurrentYear, calCurrentMonth, day);
        const yyyymmdd = `${calCurrentYear}-${String(calCurrentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        
        let cellClass = 'calendar-day-btn';
        const isPast = cellDate < today;

        if (isPast) {
            cellClass += ' is-disabled';
        } else if (checkInDate && checkOutDate) {
            const cellTime = cellDate.getTime();
            const inTime = checkInDate.getTime();
            const outTime = checkOutDate.getTime();

            if (cellTime === inTime || cellTime === outTime) {
                cellClass += ' is-selected';
            } else if (cellTime > inTime && cellTime < outTime) {
                cellClass += ' in-range';
            }
        }

        html += `
            <button type="button" class="${cellClass}" onclick="selectCalendarCellDate('${yyyymmdd}')" ${isPast ? 'disabled' : ''}>
                ${day}
            </button>
        `;
    }

    daysGrid.innerHTML = html;
}

function selectCalendarCellDate(dateStr) {
    const checkInInput = document.getElementById('modalCheckInInput');
    const checkOutInput = document.getElementById('modalCheckOutInput');
    if (!checkInInput || !checkOutInput) return;

    const checkIn = new Date(checkInInput.value);
    const selected = new Date(dateStr);

    if (!checkInInput.dataset.selectingState || checkInInput.dataset.selectingState === 'end') {
        checkInInput.value = dateStr;
        checkInInput.dataset.selectingState = 'start';
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
    const inlineBadge = document.getElementById('inlineStayDurationBadge');

    if (!checkInVal || !checkOutVal) return;

    const inD = new Date(checkInVal);
    const outD = new Date(checkOutVal);

    if (isNaN(inD) || isNaN(outD) || outD <= inD) {
        if (badge) badge.textContent = "Select valid dates";
        if (inlineBadge) inlineBadge.textContent = "Select valid dates";
        return;
    }

    const nights = Math.round((outD - inD) / (1000 * 60 * 60 * 24));
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const text = `${monthNames[inD.getMonth()]} ${inD.getDate()} – ${monthNames[outD.getMonth()]} ${outD.getDate()} (${nights}-Night Stay)`;
    if (badge) badge.textContent = text;
    if (inlineBadge) inlineBadge.textContent = text;
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

    const searchForm = document.getElementById('availabilitySearchForm') || document.getElementById('bookingForm');
    if (searchForm) {
        searchForm.submit();
    }
}
</script>

<style>
.calendar-grid-header,
.calendar-grid-days {
    display: grid !important;
    grid-template-columns: repeat(7, 1fr) !important;
    gap: 8px !important;
}

.calendar-day-btn {
    width: 100%;
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    color: #F8FAFC;
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(212, 175, 55, 0.25);
    cursor: pointer;
    transition: all 0.2s ease;
}

.calendar-day-btn:hover:not(.is-disabled) {
    background: rgba(212, 175, 55, 0.3) !important;
    border-color: #D4AF37 !important;
    color: #FFDF73 !important;
    transform: scale(1.06);
}

.calendar-day-btn.is-selected {
    background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%) !important;
    color: #070A10 !important;
    border: none !important;
    font-weight: 900 !important;
    box-shadow: 0 4px 15px rgba(212, 175, 55, 0.6) !important;
}

.calendar-day-btn.in-range {
    background: rgba(212, 175, 55, 0.25) !important;
    border: 1px solid rgba(212, 175, 55, 0.5) !important;
    color: #FFDF73 !important;
}

.calendar-day-btn.is-disabled {
    opacity: 0.3;
    cursor: not-allowed;
    background: rgba(15, 23, 42, 0.4);
    border-color: transparent;
}

.calendar-day-empty {
    aspect-ratio: 1 / 1;
    opacity: 0;
}
</style>

<?php
}

function renderInlineCalendarWidget(string $checkInVal = '', string $checkOutVal = ''): void
{
    $today = new DateTimeImmutable('today');
    $checkIn = $checkInVal ?: $today->format('Y-m-d');
    $checkOut = $checkOutVal ?: $today->modify('+1 day')->format('Y-m-d');
?>
<div class="card bg-dark text-light rounded-4 p-4 shadow-lg my-4" id="inlineCalendarSection" style="background: rgba(15, 23, 42, 0.92) !important; backdrop-filter: blur(25px); border: 1px solid rgba(212, 175, 55, 0.45) !important; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5) !important;">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h3 class="font-serif fw-bold m-0" style="color: #FFDF73 !important; text-shadow: 0 2px 10px rgba(212, 175, 55, 0.3);"><i class="bi bi-calendar-range me-2"></i>Select Your Stay Dates</h3>
            <p class="text-light opacity-90 small m-0 fw-semibold">Click your check-in and check-out dates on the interactive calendar grid below.</p>
        </div>
        <div id="inlineStayDurationBadge" class="badge bg-gold text-dark fs-6 px-4 py-2 fw-bold rounded-pill shadow" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%) !important; color: #070A10 !important; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);">
            Select dates below
        </div>
    </div>

    <form action="rooms.php" method="GET" id="inlineCalendarForm">
        <div class="row g-3 mb-4">
            <div class="col-md-5">
                <label class="form-label text-xs text-uppercase tracking-wider text-light opacity-90 fw-bold"><i class="bi bi-box-arrow-in-right text-warning me-1"></i>Check-In Date</label>
                <input type="date" name="check_in" id="modalCheckInInput" class="form-control border-warning text-light fw-bold py-2" value="<?= e($checkIn) ?>" min="<?= $today->format('Y-m-d') ?>" style="background: rgba(30, 41, 59, 0.85); border: 1px solid rgba(212, 175, 55, 0.5);">
            </div>
            <div class="col-md-5">
                <label class="form-label text-xs text-uppercase tracking-wider text-light opacity-90 fw-bold"><i class="bi bi-box-arrow-right text-warning me-1"></i>Check-Out Date</label>
                <input type="date" name="check_out" id="modalCheckOutInput" class="form-control border-warning text-light fw-bold py-2" value="<?= e($checkOut) ?>" min="<?= $today->modify('+1 day')->format('Y-m-d') ?>" style="background: rgba(30, 41, 59, 0.85); border: 1px solid rgba(212, 175, 55, 0.5);">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn w-100 rounded-pill py-2 font-serif fw-bold shadow" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10; border: none; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);">
                    <i class="bi bi-search me-1"></i>Search Rooms
                </button>
            </div>
        </div>
    </form>

    <!-- Visual Interactive 7-Column Calendar Month Grid -->
    <div id="calendarVisualGrid" class="p-4 rounded-4 border shadow-inner" style="background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(212, 175, 55, 0.35) !important;">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <button type="button" class="btn btn-sm btn-outline-warning rounded-circle" onclick="shiftCalendarMonth(-1)" style="width: 40px; height: 40px; color: #FFDF73; border-color: rgba(212, 175, 55, 0.5);"><i class="bi bi-chevron-left"></i></button>
            <h4 class="m-0 font-serif fw-bold fs-3 text-center" id="calendarMonthTitle" style="color: #FFDF73; text-shadow: 0 2px 8px rgba(0,0,0,0.5);">July 2026</h4>
            <button type="button" class="btn btn-sm btn-outline-warning rounded-circle" onclick="shiftCalendarMonth(1)" style="width: 40px; height: 40px; color: #FFDF73; border-color: rgba(212, 175, 55, 0.5);"><i class="bi bi-chevron-right"></i></button>
        </div>
        
        <!-- 7-Column Weekday Header -->
        <div class="calendar-grid-header mb-3 text-center font-serif fw-bold text-uppercase fs-6">
            <div style="color: #FBBF24;">Sun</div>
            <div style="color: #F8FAFC;">Mon</div>
            <div style="color: #F8FAFC;">Tue</div>
            <div style="color: #F8FAFC;">Wed</div>
            <div style="color: #F8FAFC;">Thu</div>
            <div style="color: #F8FAFC;">Fri</div>
            <div style="color: #FBBF24;">Sat</div>
        </div>
        
        <!-- 7-Column Days Grid -->
        <div class="calendar-grid-days" id="calendarDaysGrid"></div>
    </div>
</div>
<?php
}

