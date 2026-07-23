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
  <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
    <div class="modal-content rounded-4 shadow-lg calendar-modal-content">
      <div class="modal-header border-bottom px-4 py-3">
        <div>
            <h4 class="font-serif fw-bold m-0"><i class="bi bi-calendar-range me-2"></i>Select Stay Dates</h4>
            <p class="opacity-75 text-xs m-0 fw-semibold">Click check-in and check-out dates on the grid below.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-4">
        <form action="javascript:void(0);" method="GET" id="modalCalendarForm">
            <div class="row g-3 mb-4 align-items-end">
                <div class="col-12 col-sm-6">
                    <label class="form-label text-xs text-uppercase tracking-wider opacity-90 fw-bold mb-1"><i class="bi bi-box-arrow-in-right text-warning me-1"></i>Check-In</label>
                    <input type="date" name="check_in" id="modalCheckInInput" class="form-control form-control-sm calendar-modal-input fw-bold py-2" value="<?= e($checkIn) ?>" min="<?= $today->format('Y-m-d') ?>" onchange="handleAutoCalendarDateUpdate()">
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label text-xs text-uppercase tracking-wider opacity-90 fw-bold mb-1"><i class="bi bi-box-arrow-right text-warning me-1"></i>Check-Out</label>
                    <input type="date" name="check_out" id="modalCheckOutInput" class="form-control form-control-sm calendar-modal-input fw-bold py-2" value="<?= e($checkOut) ?>" min="<?= $today->modify('+1 day')->format('Y-m-d') ?>" onchange="handleAutoCalendarDateUpdate()">
                </div>
            </div>
        </form>

        <!-- Visual Interactive 7-Column Calendar Month Grid -->
        <div id="calendarVisualGrid" class="calendar-visual-grid p-4 shadow-sm" style="max-width: 500px; width: 100%; margin: 0 auto;">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <button type="button" class="btn btn-sm calendar-month-nav-btn d-flex align-items-center justify-content-center" onclick="shiftCalendarMonth(-1)"><i class="bi bi-chevron-left"></i></button>
                <h5 class="m-0 font-serif fw-bold fs-5 text-center calendar-month-title" id="calendarMonthTitle">July 2026</h5>
                <button type="button" class="btn btn-sm calendar-month-nav-btn d-flex align-items-center justify-content-center" onclick="shiftCalendarMonth(1)"><i class="bi bi-chevron-right"></i></button>
            </div>
            
            <!-- 7-Column Weekday Header -->
            <div class="calendar-grid-header mb-3 text-center fw-bold text-uppercase text-xs">
                <div>SUN</div>
                <div>MON</div>
                <div>TUE</div>
                <div>WED</div>
                <div>THU</div>
                <div>FRI</div>
                <div>SAT</div>
            </div>
            
            <!-- Days Grid -->
            <div class="calendar-grid-days text-center" id="calendarDaysGrid"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
    renderCalendarPickerAssets();
}

function renderCalendarPickerAssets(): void
{
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
?>
<script>
let calCurrentYear = 2026;
let calCurrentMonth = 6; // 0-indexed: 6 = July

function parseLocalDate(dateStr) {
    if (!dateStr) return null;
    const parts = dateStr.split('-');
    if (parts.length !== 3) return null;
    return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
}

function formatLocalDate(d) {
    if (!d || isNaN(d)) return '';
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function initCalendarApp() {
    const checkInVal = (document.getElementById('modalCheckInInput') || document.querySelector('input[name="check_in"]'))?.value;
    if (checkInVal) {
        const d = parseLocalDate(checkInVal);
        if (d && !isNaN(d)) {
            calCurrentYear = d.getFullYear();
            calCurrentMonth = d.getMonth();
        }
    }
    renderVisualCalendarGrid();
    updateStayDurationBadge();

    document.querySelectorAll('#modalCheckInInput, input[name="check_in"]').forEach(el => {
        el.addEventListener('change', () => {
            updateStayDurationBadge();
            renderVisualCalendarGrid();
        });
    });
    document.querySelectorAll('#modalCheckOutInput, input[name="check_out"]').forEach(el => {
        el.addEventListener('change', () => {
            updateStayDurationBadge();
            renderVisualCalendarGrid();
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCalendarApp);
} else {
    initCalendarApp();
}

setTimeout(initCalendarApp, 100);
setTimeout(initCalendarApp, 500);

function renderVisualCalendarGrid() {
    const monthTitles = document.querySelectorAll('#calendarMonthTitle, .calendar-month-title');
    const daysGrids = document.querySelectorAll('#calendarDaysGrid, .calendar-grid-days');
    if (!daysGrids.length) return;

    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    monthTitles.forEach(el => {
        el.textContent = `${monthNames[calCurrentMonth]} ${calCurrentYear}`;
    });

    const firstDay = new Date(calCurrentYear, calCurrentMonth, 1).getDay();
    const totalDays = new Date(calCurrentYear, calCurrentMonth + 1, 0).getDate();

    const checkInInputVal = (document.getElementById('modalCheckInInput') || document.querySelector('input[name="check_in"]'))?.value;
    const checkOutInputVal = (document.getElementById('modalCheckOutInput') || document.querySelector('input[name="check_out"]'))?.value;
    const checkInDate = parseLocalDate(checkInInputVal);
    const checkOutDate = parseLocalDate(checkOutInputVal);
    const today = new Date();
    today.setHours(0,0,0,0);

    let html = '';
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

    daysGrids.forEach(grid => {
        grid.innerHTML = html;
    });
}

function selectCalendarCellDate(dateStr) {
    const checkInInputs = document.querySelectorAll('input[name="check_in"], #modalCheckInInput');
    const checkOutInputs = document.querySelectorAll('input[name="check_out"], #modalCheckOutInput');
    if (!checkInInputs.length || !checkOutInputs.length) return;

    const selected = parseLocalDate(dateStr);
    const primaryCheckIn = checkInInputs[0];
    const checkIn = parseLocalDate(primaryCheckIn.value);

    if (!primaryCheckIn.dataset.selectingState || primaryCheckIn.dataset.selectingState === 'end') {
        const nextDay = new Date(selected);
        nextDay.setDate(nextDay.getDate() + 1);
        checkInInputs.forEach(i => { i.value = formatLocalDate(selected); i.dataset.selectingState = 'start'; });
        checkOutInputs.forEach(i => { i.value = formatLocalDate(nextDay); });
    } else {
        if (selected <= checkIn) {
            const nextDay = new Date(selected);
            nextDay.setDate(nextDay.getDate() + 1);
            checkInInputs.forEach(i => { i.value = formatLocalDate(selected); });
            checkOutInputs.forEach(i => { i.value = formatLocalDate(nextDay); });
        } else {
            checkOutInputs.forEach(i => { i.value = formatLocalDate(selected); });
            checkInInputs.forEach(i => { i.dataset.selectingState = 'end'; });
            updateStayDurationBadge();
            renderVisualCalendarGrid();
            handleAutoCalendarDateUpdate();
            return;
        }
    }

    updateStayDurationBadge();
    renderVisualCalendarGrid();
    handleAutoCalendarDateUpdate();
}

function handleAutoCalendarDateUpdate() {
    const checkIn = (document.getElementById('modalCheckInInput') || document.querySelector('input[name="check_in"]'))?.value;
    const checkOut = (document.getElementById('modalCheckOutInput') || document.querySelector('input[name="check_out"]'))?.value;
    if (!checkIn || !checkOut) return;

    updateStayDurationBadge();

    if (typeof updateHotelMapAvailability === 'function') {
        updateHotelMapAvailability(checkIn, checkOut);
    }

    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('check_in', checkIn);
    urlParams.set('check_out', checkOut);

    if (window.location.pathname.includes('dashboard.php') || window.location.pathname.includes('room-detail.php')) {
        window.location.search = urlParams.toString();
    } else {
        window.history.replaceState({}, '', `${window.location.pathname}?${urlParams.toString()}`);
    }
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
    const checkInVal = (document.getElementById('modalCheckInInput') || document.querySelector('input[name="check_in"]'))?.value;
    const checkOutVal = (document.getElementById('modalCheckOutInput') || document.querySelector('input[name="check_out"]'))?.value;
    const badge = document.getElementById('stayDurationBadge');
    const inlineBadge = document.getElementById('inlineStayDurationBadge');

    if (!checkInVal || !checkOutVal) return;

    const inD = parseLocalDate(checkInVal);
    const outD = parseLocalDate(checkOutVal);

    if (!inD || !outD || isNaN(inD) || isNaN(outD) || outD <= inD) {
        if (badge) badge.textContent = "Select valid dates";
        if (inlineBadge) inlineBadge.textContent = "Select valid dates";
        return;
    }

    const nights = Math.round((outD.getTime() - inD.getTime()) / (1000 * 60 * 60 * 24));
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const text = `${monthNames[inD.getMonth()]} ${inD.getDate()} – ${monthNames[outD.getMonth()]} ${outD.getDate()} (${nights}-Night Stay)`;
    if (badge) badge.textContent = text;
    if (inlineBadge) inlineBadge.textContent = text;
}
function handleInlineCalendarSearch() {
    const checkIn = (document.getElementById('modalCheckInInput') || document.querySelector('input[name="check_in"]'))?.value;
    const checkOut = (document.getElementById('modalCheckOutInput') || document.querySelector('input[name="check_out"]'))?.value;
    if (!checkIn || !checkOut) return;

    if (typeof updateHotelMapAvailability === 'function') {
        updateHotelMapAvailability(checkIn, checkOut);
    }

    const newUrl = `${window.location.pathname}?check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}`;
    window.history.pushState({ checkIn, checkOut }, '', newUrl);

    const mapEl = document.querySelector('.hotel-map-container');
    if (mapEl) {
        mapEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}
</script>

<style>
/* ===== DARK MODE DEFAULT STYLES ===== */
.inline-calendar-card {
    background: rgba(15, 23, 42, 0.92) !important;
    backdrop-filter: blur(25px) !important;
    border: 1px solid rgba(212, 175, 55, 0.45) !important;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5) !important;
    color: #f8fafc !important;
}

.calendar-widget-title {
    color: #FFDF73 !important;
    text-shadow: 0 2px 10px rgba(212, 175, 55, 0.3) !important;
}

.calendar-visual-grid {
    background: rgba(30, 41, 59, 0.85) !important;
    border: 1px solid rgba(212, 175, 55, 0.35) !important;
    border-radius: 20px !important;
}

.calendar-month-title {
    color: #FFDF73 !important;
    font-weight: 800 !important;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5) !important;
}

.calendar-month-nav-btn {
    width: 36px;
    height: 36px;
    border: 1px solid rgba(212, 175, 55, 0.5) !important;
    color: #FFDF73 !important;
    background: transparent !important;
    border-radius: 50% !important;
}

.calendar-grid-header {
    display: grid !important;
    grid-template-columns: repeat(7, 1fr) !important;
    gap: 6px !important;
    max-width: 520px !important;
    margin: 0 auto !important;
}

.calendar-grid-header div {
    color: #F8FAFC !important;
    font-weight: 800 !important;
    font-size: 0.85rem !important;
}

.calendar-grid-header div:first-child,
.calendar-grid-header div:last-child {
    color: #FBBF24 !important;
}

.calendar-grid-days {
    display: grid !important;
    grid-template-columns: repeat(7, 1fr) !important;
    gap: 6px !important;
    max-width: 520px !important;
    margin: 0 auto !important;
}

.calendar-day-btn {
    width: 100%;
    max-width: 44px;
    height: 44px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    color: #F8FAFC !important;
    background: rgba(30, 41, 59, 0.7) !important;
    border: 1px solid rgba(212, 175, 55, 0.25) !important;
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
    background: rgba(15, 23, 42, 0.4) !important;
    border-color: transparent !important;
    color: #64748b !important;
}

.calendar-day-empty {
    width: 100%;
    max-width: 44px;
    height: 44px;
    margin: 0 auto;
    opacity: 0;
}

.calendar-input-label {
    color: #f8fafc !important;
}

.calendar-date-input {
    background: rgba(30, 41, 59, 0.85) !important;
    color: #f8fafc !important;
    border: 1px solid rgba(212, 175, 55, 0.5) !important;
}

/* ===== LIGHT MODE OVERRIDES ===== */
body.light-mode .inline-calendar-card,
body.light-mode #inlineCalendarSection,
body.light-mode #calendarPickerModal .modal-content {
    background: #ffffff !important;
    border: 1px solid rgba(180, 140, 60, 0.22) !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05) !important;
    color: #0f172a !important;
}

body.light-mode .calendar-input-label {
    color: #0f172a !important;
}

body.light-mode .calendar-date-input {
    background: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #cbd5e1 !important;
}

body.light-mode .calendar-visual-grid,
body.light-mode #calendarVisualGrid {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 20px !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
}

body.light-mode .calendar-widget-title {
    color: #b45309 !important;
    text-shadow: none !important;
}

body.light-mode .calendar-month-title {
    color: #0f172a !important;
    text-shadow: none !important;
}

body.light-mode .calendar-month-nav-btn {
    border: 1px solid #cbd5e1 !important;
    color: #f59e0b !important;
    background: #ffffff !important;
}

body.light-mode .calendar-grid-header div {
    color: #0f172a !important;
}

body.light-mode .calendar-day-btn {
    background: #f8fafc !important;
    color: #1e293b !important;
    border: 1px solid #e2e8f0 !important;
}

body.light-mode .calendar-day-btn:hover:not(.is-disabled) {
    background: #fef08a !important;
    border-color: #f59e0b !important;
    color: #0f172a !important;
}

body.light-mode .calendar-day-btn.is-selected {
    background: #fef08a !important;
    color: #0f172a !important;
    border: 2px solid #f59e0b !important;
    font-weight: 900 !important;
    box-shadow: 0 0 18px rgba(245, 158, 11, 0.45) !important;
}

body.light-mode .calendar-day-btn.in-range {
    background: #fffbeb !important;
    border: 1px solid #fde68a !important;
    color: #92400e !important;
}

body.light-mode .calendar-day-btn.is-disabled {
    background: #f1f5f9 !important;
    color: #94a3b8 !important;
    border-color: transparent !important;
    opacity: 0.35;
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
<div class="card inline-calendar-card rounded-4 p-4 shadow-lg h-100 my-0" id="inlineCalendarSection" style="max-width: 650px; width: 100%; margin: 0 auto; backdrop-filter: blur(25px);">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h4 class="font-serif fw-bold m-0 calendar-widget-title"><i class="bi bi-calendar-range me-2"></i>Select Stay Dates</h4>
            <p class="calendar-widget-subtitle text-xs m-0 fw-semibold">Click check-in and check-out dates on the grid below.</p>
        </div>
        <div id="inlineStayDurationBadge" class="badge bg-gold text-dark fs-6 px-3 py-2 fw-bold rounded-pill shadow stay-duration-badge" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%) !important; color: #070A10 !important; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);">
            Select dates below
        </div>
    </div>

    <form action="javascript:void(0);" method="GET" id="inlineCalendarForm" onsubmit="event.preventDefault(); handleInlineCalendarSearch();">
        <div class="row g-2 mb-4 align-items-end">
            <div class="col-12 col-sm-5 col-lg-5">
                <label class="form-label text-xs text-uppercase tracking-wider fw-bold mb-1 calendar-input-label"><i class="bi bi-box-arrow-in-right text-warning me-1"></i>Check-In</label>
                <input type="date" name="check_in" id="modalCheckInInput" class="form-control form-control-sm border-warning fw-bold py-2 calendar-date-input" value="<?= e($checkIn) ?>" min="<?= $today->format('Y-m-d') ?>">
            </div>
            <div class="col-12 col-sm-5 col-lg-5">
                <label class="form-label text-xs text-uppercase tracking-wider fw-bold mb-1 calendar-input-label"><i class="bi bi-box-arrow-right text-warning me-1"></i>Check-Out</label>
                <input type="date" name="check_out" id="modalCheckOutInput" class="form-control form-control-sm border-warning fw-bold py-2 calendar-date-input" value="<?= e($checkOut) ?>" min="<?= $today->modify('+1 day')->format('Y-m-d') ?>">
            </div>
            <div class="col-12 col-sm-2 col-lg-2 mt-2 mt-sm-0">
                <button type="submit" class="btn btn-sm w-100 rounded-pill py-2 font-serif fw-bold shadow" style="background: linear-gradient(135deg, #D4AF37 0%, #FFDF73 50%, #AA7C11 100%); color: #070A10; border: none; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);">
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
        </div>
    </form>

    <!-- Visual Interactive 7-Column Calendar Month Grid -->
    <div id="calendarVisualGrid" class="calendar-visual-grid p-3 rounded-4 border shadow-inner" style="max-width: 520px; width: 100%; margin: 0 auto;">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <button type="button" class="btn btn-sm calendar-month-nav-btn rounded-circle d-flex align-items-center justify-content-center" onclick="shiftCalendarMonth(-1)"><i class="bi bi-chevron-left"></i></button>
            <h5 class="m-0 font-serif fw-bold fs-5 text-center calendar-month-title" id="calendarMonthTitle">July 2026</h5>
            <button type="button" class="btn btn-sm calendar-month-nav-btn rounded-circle d-flex align-items-center justify-content-center" onclick="shiftCalendarMonth(1)"><i class="bi bi-chevron-right"></i></button>
        </div>
        
        <!-- 7-Column Weekday Header -->
        <div class="calendar-grid-header mb-2 text-center font-serif fw-bold text-uppercase text-xs">
            <div>SUN</div>
            <div>MON</div>
            <div>TUE</div>
            <div>WED</div>
            <div>THU</div>
            <div>FRI</div>
            <div>SAT</div>
        </div>
        
        <!-- 7-Column Days Grid -->
        <div class="calendar-grid-days" id="calendarDaysGrid"></div>
    </div>
</div>
<?php
    renderCalendarPickerAssets();
}

