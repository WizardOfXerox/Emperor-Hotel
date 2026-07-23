# Dashboard Feature Tracker

## Scope

This tracker covers the hotel reservation and management dashboard, connected admin modules, and customer booking flow.

Current source files:

- `public/admin/dashboard.php`
- `public/admin/rooms.php`
- `public/admin/reservations.php`
- `public/admin/booking-records.php`
- `public/admin/payments.php`
- `public/admin/guests.php`
- `public/admin/receipt.php`
- `public/admin/reports.php`
- `public/admin/users.php`
- `public/user/dashboard.php`
- `public/user/payment.php`
- `public/assets/css/app.css`
- `public/assets/css/admin/*.css`
- `public/assets/css/auth/*.css`
- `public/assets/css/site/*.css`
- `public/assets/css/user/*.css`
- `public/assets/vendor/chartjs/chart.umd.min.js`
- `app/models/User.php`
- `app/models/Guest.php`
- `app/models/Room.php`
- `app/models/Reservation.php`
- `app/models/Payment.php`
- `database/schema.sql`
- `database/seed_rooms.sql`
- `app/models/SupportAssistant.php`
- `app/config/hotel.php`
- `public/support/api.php`
- `public/assets/js/support-widget.js`

## Status Legend

- `Done`: implemented and connected to the database
- `Partial`: implemented but still missing important workflow pieces
- `Missing`: not built yet
- `Future`: useful enhancement, but not required for the current base system

Current unresolved tracker rows:

- `Missing`: 0
- `Partial`: 0

## Current Baseline

- The app is now PHP-based; old redirect-only `.html` files were removed.
- Admin pages require login and admin role access.
- Dashboard data is loaded from MySQL through model classes.
- Chart.js is stored locally and used by the dashboard without Node.js.
- Room, reservation, payment, and user admin modules are connected to the database.
- The support widget uses a hybrid local-database-first strategy: customer questions about rooms, availability, pricing, policies, and booking instructions are answered from live database queries and built-in FAQ datasets; admin questions about dashboard metrics, sales, occupancy, and operational alerts are answered from live admin statistics. Only open-ended or conversational questions fall back to Gemini with scoped real-time database context injection.
- The AI support assistant includes step-by-step booking guides for both guest self-service reservations and admin walk-in workflows.
- Date and month range extraction supports specific month names (e.g. June, December), relative periods (yesterday, last 30 days, last 90 days), and smart year resolution based on the current system date.
- The Gemini API integration uses camelCase systemInstruction, alternating user/model role turns, and consecutive-role merging to prevent schema validation errors.
- The support widget JavaScript normalizes Windows CRLF line endings before Markdown table parsing and renders tables as structured HTML with correct column alignment.
- Room type inclusions are stored as simple PHP catalog descriptions, not as a separate database table.
- Room XML import/export is implemented with DOMDocument.
- Public home and rooms pages are PHP pages backed by room catalog data and database-driven starting prices.
- Shared CSS now lives in `public/assets/css/app.css`, while each visual page group has page-specific CSS files for easier debugging.
- Dashboard alerts show overdue check-outs, failed payments, and overlap conflicts.
- The admin `Reservations` page is now create-only, while existing reservation management is separated into the `Booking Records` tab.
- Walk-in front desk actions now live inside a per-reservation Manage modal on `booking-records.php`, keeping the records table compact while still supporting confirm, check-in, extend stay, check-out, cancel, receipt, payment, and delete actions.
- User and admin reservation forms now require a manual room-card selection so the guest clearly chooses the room.
- Reservation and room UIs no longer ask for adult/child split counts; room cards present a simple "good for up to 5 people" display note.
- Admin reservation forms can filter room cards by check-in/check-out dates before selecting a room.
- Admin reports show filterable occupancy, confirmed revenue, and reservation trend tables.
- Customer reservations can choose a payment route from the user dashboard.
- Customer booking now uses a wide two-column form: stay details, room inclusions, and payment on the left with room selection cards on the right, with booking history moved below.
- Room selection cards show the room number in the yellow badge and use three cards per row on desktop.
- Customer non-cash payments use a customer-safe simulated payment page instead of the admin payments module.
- Guest search/history and printable reservation receipts are available from the admin area.
- Payment rules prevent active pending/confirmed payments from exceeding the reservation total.
- Server-side validation covers reservation dates, date overlaps, guest records, room records, statuses, room prices, guest emails, user emails, and payment amounts.

## Feature Tracker

| Area | Feature | Status | Current State | Next Step |
| --- | --- | --- | --- | --- |
| Dashboard Overview | Sidebar navigation | Done | Dashboard, Rooms, Reservations, Booking Records, Payments, Guests, Reports, and Users pages are connected through the admin layout | Keep labels/routes updated as modules grow |
| Dashboard Overview | KPI cards | Done | Users, customers this month, revenue this month, available rooms, pending reservations, and upcoming check-outs load live values | Add arrivals today and departures today if needed |
| Dashboard Overview | Monthly performance chart | Done | Chart.js combo chart shows monthly rooms booked and confirmed revenue | Add more months or date filters later |
| Dashboard Overview | Monthly performance table | Done | Table uses live monthly reservation/payment query data | Add empty-state guidance if desired |
| Dashboard Overview | Reservation status chart | Done | Chart.js doughnut chart uses reservation status counts | Add drill-down links later |
| Dashboard Overview | Room status chart | Done | Chart.js doughnut chart uses room status counts | Add click-through to filtered room table later |
| Dashboard Overview | Payment status chart | Done | Chart.js doughnut chart uses payment status counts | Add payment date filters later |
| Dashboard Overview | Alert panel | Done | Dashboard watchlist shows overdue check-outs, failed payments, and overlapping active reservation conflicts | Add notification delivery only if needed |
| AI Support | Customer support chat widget | Done | The support widget answers customer room availability, room type, room price, hotel profile, and booking guide questions from the live database using pattern matching and phrase-coverage scoring; formats responses as HTML tables; and falls back to Gemini with real-time room catalog and hotel profile context for open-ended queries | Add conversation logging only if needed |
| AI Support | Admin support chat widget | Done | The support widget compiles live dashboard counters, monthly performance snapshots, room inventory status, operational alerts, and range-specific revenue/occupancy data for admin questions; supports specific month names, yesterday, and last 30/90 days date parsing; and injects this real-time context into Gemini for open-ended admin queries | Add scoped audit logging later |
| AI Support | Booking guide instructions | Done | The AI provides step-by-step guest booking instructions (login, dashboard, dates, room selection, payment, tracking) and admin walk-in reservation instructions (reservation creation, booking records management, modal actions) | Add visual screenshots later if desired |
| AI Support | Date/month range parsing | Done | Admin queries support specific month names, relative periods (yesterday, last 30/90 days), smart year resolution, and explicit date formats for targeted data retrieval | Add custom date range input UI later if desired |
| AI Support | Gemini API protocol | Done | Uses camelCase systemInstruction field, alternating user/model role turns with consecutive-role merging, and scoped real-time database context injection | Monitor for Gemini API spec changes |
| AI Support | Markdown table rendering | Done | Support widget JS normalizes Windows CRLF line endings, parses consecutive pipe-delimited lines, validates table dividers, and preserves empty cell column alignment | Add sortable table headers later if desired |
| Rooms | Room listing table | Done | Admin room table loads live records from the database with room number, type, floor, rate, status, and actions | Add search/filter controls later |
| Rooms | Room CRUD | Done | Admin can create, edit, and delete room records | Add delete guard messaging when room has reservations |
| Rooms | Bulk room type price update | Done | Admin can set one price for all rooms under a selected room type | Add audit trail if required |
| Rooms | Room type catalog details | Done | Each room type has plain catalog details and included perks shown on public and reservation pages | Keep text simple for student explanation |
| Rooms | Room type support | Done | Three room types are supported: Imperial Deluxe, Royal Executive, Emperor Presidential | Keep schema/model constants in sync if room types change |
| Rooms | Room state support | Done | Available, Reserved, and Occupied are supported | Add separate housekeeping statuses later only if required |
| Rooms | XML export | Done | `rooms.php?export=xml` exports room records only | Keep XML scoped to rooms unless another table export is explicitly required |
| Rooms | XML import | Done | Admin can upload room XML and create/update room records only | Add XML preview/validation report if needed |
| Reservations | Reservation creation page | Done | Admin `reservations.php` is focused on creating new walk-in reservations only | Add guest search shortcuts later if needed |
| Booking Records | Reservation listing table | Done | Admin `booking-records.php` loads existing booking records with one Manage button per row | Add search/date filters later |
| Booking Records | Reservation record actions | Done | Admin can update status, extend active stays, open receipts, collect payments, cancel, and delete reservations from the Manage modal | Add edit workflow later only if the project needs it |
| Reservations | Room card selector | Done | User and admin reservation forms use grouped room cards with all/available/unavailable filters, green/red status dots, room-number badges, and a three-card desktop layout on the user dashboard | Add guest-count filtering only if that feature returns |
| Reservations | Date-aware availability | Done | Admin reservation form can filter room cards by selected check-in/check-out dates before choosing a room | Add live AJAX filtering later if desired |
| Reservations | Room inclusions | Done | Each room type has plain included perks shown as descriptive room information | Add editable catalog text later if required |
| Reservations | Full-name booking field | Done | User and admin reservation forms collect full name and split it into guest first/last name for storage | Add self-service profile updates later |
| Reservations | Cost tracker | Done | User and admin reservation forms show selected room, nightly rate, nights, subtotal, room inclusions, and estimated total | Add tax/discount fields later if required |
| Reservations | Reservation validation | Done | Server-side validation checks date format, no past check-ins, check-out after check-in, date-overlap room availability, valid guest/room records, status, and positive totals | Add client-side helper validation later |
| Reservations | Reservation status flow | Done | Status values exist, room status is recalculated from remaining active reservations on create/update/delete/status changes, front desk actions are opened from a per-reservation Manage modal, and fully paid pending reservations auto-confirm | Add configurable deposit-only rules later if required |
| Reservations | Stay extension | Done | Active reservations can be extended in the same room only when the added date range has no active overlap; the reservation total increases by the added room-night cost | Add room-transfer workflow later if the same room is unavailable |
| Reservations | Payment route selection | Done | New walk-in reservations can choose Cash to generate an automatic pending cash payment reference, while card, bank, online, and other methods continue to the Payments page | Add cashier-only role later if required |
| User Booking | Customer booking layout | Done | The user dashboard places stay details, room inclusions, cost tracker, and payment route beside the room selection panel; booking history is below the form | Add visual browser regression checks later if desired |
| User Booking | Customer payment route selection | Done | Logged-in customers can choose Cash to receive an automatic pending cash payment reference or choose a non-cash method to continue to the customer payment page | Add real gateway integration only if required |
| User Booking | Customer payment page | Done | Non-admin customers can submit simulated non-cash payments for their own reservations, with automatic references and Pending review status | Add more detailed card/wallet fields later if required |
| Reservations | Manual room-card selection | Done | User and admin reservation forms require a selected room card, with date-aware availability labels shown before saving | Add stronger visual grouping by floor later if desired |
| Check-In / Check-Out | Dedicated check-in/check-out actions | Done | Booking records expose one Manage button per row; the modal shows reservation details, payment totals, and controls for confirm, check-in, extend stay, check-out, cancel, receipt, payment, and delete | Build a separate arrivals board later if desired |
| Payments | Payment entry form | Done | Admin can record manual payments or simulated transactions against valid reservations, with transaction references generated automatically | Add edit/refund handling later |
| Payments | Payment cost tracker | Done | Payments page shows reservation total, confirmed paid amount, pending logs, balance due, and remaining payable amount before saving a transaction | Add clearer disabled state when fully paid |
| Payments | Payment history table | Done | Payment table loads live transactions, shows generated references, supports admin review for Pending transactions, and locks Confirmed/Failed/Refunded records as transaction history | Add filters by status/date |
| Payments | Auto-confirm fully paid reservations | Done | When confirmed payments cover the full reservation total, a Pending reservation is automatically changed to Confirmed | Add configurable deposit-only rules later if required |
| Payments | Payment summary table | Done | Payment totals by status are computed from the database | Add revenue by room type report later |
| Payments | Simulated transaction report log | Done | Payments page creates simulated transaction records and keeps pending items visible for admin review | Add filters for simulated vs manual transactions later |
| Payments | Partial payment support | Done | Confirmed and pending payments are tracked, and active pending/confirmed amounts cannot exceed the reservation total | Add refund adjustment workflows later |
| Payments | Receipt / print support | Done | Admins can open printable reservation receipts with reservation details, payment totals, balance, and transaction logs | Add PDF export later if required |
| Guests | Guest creation | Done | Guest records are created/upserted through reservations and user booking, then can be found from the Guests page | Add direct create/edit guest form later if required |
| Guests | Guest profile/history | Done | Admin Guests page shows selected guest reservation history, payment progress, and receipt links | Add editable guest profile form later |
| Guests | Guest search | Done | Admin Guests page searches guests by name, phone, or email | Add advanced filters later if required |
| Authentication | Login page | Done | Login authenticates against the users table and starts a session | Add forgot-password only if required |
| Authentication | Registration page | Done | Registration creates user accounts securely | Add email verification only if required |
| Authentication | Role-based access | Done | Admin pages require admin role; user dashboard requires login | Add more roles only if the project needs staff/cashier separation |
| Backend | Database schema | Done | `schema.sql` defines users, guests, rooms, reservations, payments, and seed data | Add migrations if schema changes become frequent |
| Backend | Existing database seed/update | Done | `seed_rooms.sql` updates existing database room types and safely seeds 36 rooms | Keep seed file aligned with room inventory decisions |
| Backend | Database connection | Done | PDO connection helper exists in `app/config/database.php` | Move credentials to `.env` later if needed |
| Backend | OOP models | Done | User, Guest, Room, Reservation, and Payment models exist | Keep business logic in models rather than pages |
| Backend | Form processing | Done | Admin/user forms submit to PHP handlers | Add CSRF tokens later for stronger security |
| Backend | Validation and error handling | Done | Server-side validation and flash messages exist across reservations, rooms, guests, users, and payments | Add CSRF tokens and more inline field hints later |
| Reports | Occupancy report | Done | Admin Reports page shows date-filtered room nights, available room nights, and occupancy rate by room type | Add export to CSV/PDF later if required |
| Reports | Revenue report | Done | Admin Reports page shows date-filtered confirmed revenue by room type and payment method | Add tax/service-charge breakdown later if required |
| Reports | Reservation trend report & Chart.js graphs | Done | Admin Reports page includes 4 interactive Chart.js visual graphs: Booking Demand Trend Line, Payment Method Revenue Share Doughnut, Suite Occupancy Bar Chart, and Guest Rating Score Chart | Add export to CSV/PDF later if required |
| Users | Phone Number & Guest Profile Sync | Done | Admin Users management collects phone numbers, synchronizes with the linked Guest model, and displays phone numbers in the Registered Accounts table | Keep profile sync intact across all actions |
| Users | SMTP Welcome Email Notification | Done | Admin user account creation automatically dispatches welcome notifications via SMTP engine with on-screen confirmation notice | Keep SMTP helper credentials updated |
| Reservations | Search & Status Filter Toolbar | Done | Real-time Search and Status Filter toolbar on Admin Reservation Records and Booking Logs pages allows filtering by guest name, room #, email, reservation ID, and status | Keep filter performance optimized |
| Reservations | Strict Check-In Payment Validation | Done | Unconfirmed / unpaid pending reservations hide the Check-In button and throw server-side validation exception requiring confirmation & payment before check-in | Enforce status transition flow |
| Reservations | Automated Credit Card Payment Confirmation | Done | Online payment gateway transactions automatically set payment status to Paid, auto-confirm reservation status, and mark room status as Reserved | Maintain automated sync |
| Frontend | Dynamic Grid Layout Switcher & Auto Mode | Done | Room catalog toolbar allows 1-5 columns grid toggle and Auto Responsive layout calculation based on screen size with LocalStorage persistence | Keep responsive breakpoints synchronized |
| Frontend | Guest View Cleaning Status Rule | Done | Internal housekeeping Cleaning status is mapped to Available for guest view so online reservations for future stay dates are not blocked | Maintain internal admin housekeeping tracking |
| Frontend | Catalog Anchor Scroll Navigation | Done | Back to Catalog links append #room-card-ID anchor hashes, smoothly scrolling back to the exact room card inspected | Keep room card IDs aligned |
| Authentication | Dual-Mode SMTP & Offline OTP Verification | Done | Registration and reservation bookings generate 6-digit OTP verification codes delivered live via Gmail SMTP socket or instantly displayed on-screen during offline localhost presentations | Supports both `SMTP_PASS` and `SMTP_PASSWORD` in `.env` |
| Reports | Advanced Executive Hospitality Analytics | Done | Reports and Dashboard display ALOS (Average Length of Stay), Booking Lead Time (days), Cancellation Loss Rate ($PHP$), and Repeat Guest Loyalty Ratio (%) | Derived dynamically via `Reservation::advancedHospitalityAnalytics()` |
| Rooms | Simplified Suite Pricing & Baseline Reset | Done | Clean 2-field Suite Pricing card (`Select Suite / Floor` + `Price / Night`) with dynamic baseline price reset (`rooms.base_price_per_night`) | 100% dynamic DB query without hardcoded array logic |
| Rooms | Floor-Based Room Number Range Validation | Done | Enforces floor-based room number limits ($N00$ to $N99$ for Floor $N$) with real-time UI hints and duplicate room number checking | Applied on both JS frontend and PHP model backend |
| Site | Luxury 5-Star Contact Us Page | Done | Interactive concierge inquiry page reading live `.env` support credentials (`SUPPORT_EMAIL`, `SUPPORT_PHONE`, `HOTEL_ADDRESS`) with instant SMTP inquiry dispatch | Integrated into primary site navigation bar |
| Frontend | Luxury Glassmorphism Modal Theme System | Done | Cohesive obsidian-gold glassmorphism modal popup theme (`#0B1120` to `#0F172A`) with gold focus glow and serif titles across all admin action dialogues | Full dark and light mode support |
| Payments | Process Refund & Ledger Audit Trail | Done | Front desk manage modal includes Process Refund action, updating remaining balance due while maintaining an immutable transaction audit log | Keep financial log records immutable |
| Payments | Standardized E-Wallet Payment Channel | Done | GCash and generic Online Payment updated to official E-Wallet category across DB schemas, payment forms, and financial reports | Maintain payment method consistency |

## Suggested Next Build Order

1. Build a separate arrivals/departures board if the front desk needs a focused daily operations page.
2. Add editable guest profile details from the Guests page.
3. Add CSV/PDF export for reports and receipts if required.
4. Add CSRF protection and more inline client-side validation hints.
