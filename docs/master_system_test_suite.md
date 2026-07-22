# 🛡️ EMPEROR HOTEL SYSTEM MASTER TEST SUITE & REGRESSION VERIFICATION MATRIX

This document serves as the **official test suite execution log** for Antigravity AI pair programming. Every test case is executed step-by-step empirically (via runtime diagnostics, PHP lint checks, HTTP status checks, and Playwright visual screenshots) before marking as **PASS** or **FAIL**.

---

## 📊 Summary Execution Matrix

- **Total Test Cases**: 20
- **Passed**: 20
- **Failed**: 0
- **Execution Date**: July 23, 2026
- **System Version**: Emperor Hotel v2.4 (Luxury Obsidian Glassmorphic Edition)

---

## 📝 Master Test Cases Log

| Test Case ID | Module | Test Scenario | Test Steps | Test Data | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-001** | Authentication | Verify valid customer login | 1. Navigate to `/public/auth/login.php`<br>2. Input registered customer email and password<br>3. Click "Log In" | **Email:** `customer@emperor.test`<br>**Password:** `password123` | User is authenticated, session established, redirected to `/public/user/dashboard.php` | Verified HTTP 200 OK, authenticated user session set, redirected to User Dashboard | **PASS** |
| **TC-002** | Authentication | Verify valid admin login | 1. Navigate to `/public/auth/login.php`<br>2. Input admin credentials<br>3. Click "Log In" | **Email:** `admin@emperor.test`<br>**Password:** `admin123` | Admin authenticated, redirected to `/public/admin/dashboard.php` | Verified HTTP 200 OK, admin role verified, redirected to Admin Dashboard | **PASS** |
| **TC-003** | Authentication | Verify invalid credentials rejection | 1. Navigate to `/public/auth/login.php`<br>2. Enter incorrect password<br>3. Click "Log In" | **Email:** `customer@emperor.test`<br>**Password:** `wrongpass` | System blocks login, displays flash error *"Invalid login credentials"* | Verified error flash message rendered, user stays on login page | **PASS** |
| **TC-004** | Authentication | Create new customer account | 1. Navigate to `/public/auth/register.php`<br>2. Fill Full Name, Email, Password<br>3. Submit form | **Name:** Juan Dela Cruz<br>**Email:** `juan@emperor.test`<br>**Password:** `Secret123!` | Account created in `users` table, redirected to login page with success flash | Verified guest user inserted into DB, success flash displayed on login | **PASS** |
| **TC-005** | Home Page | 100vh Fullscreen Hero background | 1. Navigate to `/public/site/home.php`<br>2. Inspect background layout and hero banner height | **URL:** `/public/site/home.php` | Hero image spans 100vh without bottom whitespace gaps or broken layout | Verified Playwright full-page screenshot: 100vh hero covers viewport cleanly | **PASS** |
| **TC-006** | Calendar Widget | Monthly 7-column calendar grid rendering | 1. Navigate to `/public/site/home.php`<br>2. Scroll to Select Stay Dates calendar widget | **Month:** Current / Next Month | Calendar displays standard 7-column grid with Sun-Sat headers and 44px x 44px buttons | Verified 7-column grid with proper weekday alignment and compact buttons | **PASS** |
| **TC-007** | Calendar Widget | Date range selection & local timezone parsing | 1. Click start check-in date cell (e.g. 23)<br>2. Click end check-out date cell (e.g. 31) | **Check-In:** `2026-07-23`<br>**Check-Out:** `2026-07-31` | Date range highlights 23 to 31 without UTC midnight off-by-one offset bug | Verified `parseLocalDate` helper prevents date shift; 23 to 31 highlighted accurately | **PASS** |
| **TC-008** | Side-by-Side Integration | Calendar and Floor Map 2-column layout | 1. Open home page (`/public/site/home.php`)<br>2. Observe section below hero | **Container:** `container-fluid`<br>**Columns:** `col-xl-5` & `col-xl-7` | Calendar and Interactive Floor Map display side-by-side with equal card heights (`h-100`) | Verified 2-column responsive flex grid rendering calendar left and floor map right | **PASS** |
| **TC-009** | Hotel Floor Map | Live AJAX date availability filtering | 1. Change stay dates on inline calendar<br>2. Click "Filter" / "Search" | **Check-In:** `2026-07-23`<br>**Check-Out:** `2026-07-31` | `map_availability.php` JSON API queried; Floor Map room status badges update live without reload | Verified room statuses (`Available`, `Reserved`, `Occupied`) update live in DOM via fetch API | **PASS** |
| **TC-010** | Hotel Floor Map | In-place search execution without redirect | 1. Submit search form in inline calendar widget on `home.php` | **Action:** `handleInlineCalendarSearch()` | Page stays on `home.php`, URL query parameters update via `pushState`, floor map updates live | Verified event.preventDefault() prevents hard redirect to rooms.php; URL state updated | **PASS** |
| **TC-011** | Room Showcase | Suites catalog carousel & navigation | 1. Navigate to `/public/site/rooms.php`<br>2. Click carousel next/prev buttons and test infinite scroll | **URL:** `/public/site/rooms.php` | Room showcase carousel scrolls smoothly with infinite loop and dual control buttons | Verified carousel slides continuously, next/prev controls function properly | **PASS** |
| **TC-012** | Room Detail | 5-Star luxury obsidian glassmorphism theme | 1. Navigate to `/public/site/room-detail.php?id=1`<br>2. Inspect layout, nav, and styling | **Room ID:** `1` | Luxury header navbar renders, obsidian glass cards render with gold accents and high contrast | Verified HTTP 200 OK; glassmorphism cards, gold headings, and header navbar render cleanly | **PASS** |
| **TC-013** | Room Detail | Interactive gallery thumbnail switcher | 1. On `room-detail.php`, click any photo thumbnail below hero image | **Thumbnails:** 4 photo thumbnails | Main room hero photo updates smoothly to clicked image with gold border highlight | Verified `switchHeroImage(src)` swaps main hero image and updates border styling | **PASS** |
| **TC-014** | Room Detail | Stay duration total price calculator | 1. Open `room-detail.php?id=1&check_in=2026-07-23&check_out=2026-07-31` | **Nights:** 8 Nights<br>**Rate:** ₱8,500/night | Total stay price calculates automatically (**₱68,000.00**) and displays duration badge | Verified 8 nights x ₱8,500 = ₱68,000 total rate calculated and displayed | **PASS** |
| **TC-015** | User Dashboard | Prefill stay dates and room choice from GET | 1. Open `/public/user/dashboard.php?selected_room=1&check_in=2026-07-26&check_out=2026-07-31` | **Query Params:** `selected_room=1&check_in=2026-07-26&check_out=2026-07-31` | Check-in/out inputs prefill with dates, Room #101 card is checked and highlighted | Verified inputs populated, Room #101 radio checked, card highlighted on load | **PASS** |
| **TC-016** | User Dashboard | Dynamic cost tracker & inclusion calculation | 1. Verify cost tracker panel on prefilled `dashboard.php` | **Selected:** Room #101<br>**Dates:** 5 Nights | Cost tracker calculates subtotal (5 nights x ₱8,500 = ₱42,500.00) and populates inclusions | Verified cost tracker elements updated with 5 nights, ₱42,500.00 total, and perks | **PASS** |
| **TC-017** | User Dashboard | Submit stay reservation & payment reference | 1. Select payment mode (Cash/Card)<br>2. Click "Submit Reservation" | **Payment:** Cash | Reservation saved with status "Pending", payment reference generated, user notified | Verified DB insertion into `reservations` & `payments`, transaction reference created | **PASS** |
| **TC-018** | Guest Reviews | Submit star rating and review feedback | 1. Open User Dashboard (`/public/user/dashboard.php`)<br>2. Click "Review Stay"<br>3. Fill rating & feedback | **Rating:** 5 Stars<br>**Comment:** "Exceptional stay!" | Review recorded in `reviews` table, average room rating updated on showcase | Verified `reviews` record created, star rating reflected on room detail page | **PASS** |
| **TC-019** | Admin Rooms | Room inventory management & no undefined variables | 1. Navigate to `/public/admin/rooms.php`<br>2. Test filters, pagination, and room creation | **Filters:** All Rooms | Page loads cleanly with zero PHP warnings/errors (`$perPage` defined before `$filters`) | Verified HTTP 200 OK; `$perPage` initialized prior to `$filters` array | **PASS** |
| **TC-020** | Admin Data Export | Export room inventory to XML | 1. On `/public/admin/rooms.php`, click "Export XML" | **Action:** `export=xml` | XML file (`rooms-export.xml`) generated and downloaded with valid XML schema | Verified header `Content-Type: application/xml`, XML content generated cleanly | **PASS** |

---

## 🛠️ Automated Regression Protocol for AI Agent

When implementing new changes, the AI developer follows this strict protocol:
1. **Lint Verification**: Execute `php -l` on all modified files to ensure zero syntax errors.
2. **Runtime Verification**: Execute `Invoke-WebRequest` to confirm HTTP 200 OK status codes.
3. **Visual Verification**: Run Playwright headless screenshots and visually inspect via `view_file`.
4. **Log Update**: Update this `master_system_test_suite.md` file with true empirical results.
5. **Git Sync**: Commit and push all verified changes to GitHub `main` branch.
