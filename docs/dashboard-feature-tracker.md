# Dashboard Feature Tracker

## Scope

This tracker covers the hotel reservation admin dashboard and connected admin modules.

Current source files:

- `public/admin/dashboard.php`
- `public/admin/rooms.php`
- `public/admin/reservations.php`
- `public/admin/payments.php`
- `public/admin/users.php`
- `public/assets/css/app.css`
- `public/assets/css/admin/dashboard.css`
- `public/assets/vendor/chartjs/chart.umd.min.js`
- `app/models/User.php`
- `app/models/Guest.php`
- `app/models/Room.php`
- `app/models/Reservation.php`
- `app/models/Payment.php`
- `database/schema.sql`
- `database/seed_rooms.sql`

## Status Legend

- `Done`: implemented and connected to the database
- `Partial`: implemented but still missing important workflow pieces
- `Missing`: not built yet
- `Future`: useful enhancement, but not required for the current base system

## Current Baseline

- The app is now PHP-based; old redirect-only `.html` files were removed.
- Admin pages require login and admin role access.
- Dashboard data is loaded from MySQL through model classes.
- Chart.js is stored locally and used by the dashboard without Node.js.
- Room, reservation, payment, and user admin modules are connected to the database.
- Room XML import/export is implemented with DOMDocument.
- Public home and rooms pages are PHP pages backed by room catalog data and live room records.

## Feature Tracker

| Area | Feature | Status | Current State | Next Step |
| --- | --- | --- | --- | --- |
| Dashboard Overview | Sidebar navigation | Done | Dashboard, Rooms, Reservations, Payments, and Users pages are connected through the admin layout | Keep labels/routes updated as modules grow |
| Dashboard Overview | KPI cards | Done | Users, customers this month, revenue this month, available rooms, pending reservations, and upcoming check-outs load live values | Add arrivals today and departures today if needed |
| Dashboard Overview | Monthly performance chart | Done | Chart.js combo chart shows monthly rooms booked and confirmed revenue | Add more months or date filters later |
| Dashboard Overview | Monthly performance table | Done | Table uses live monthly reservation/payment query data | Add empty-state guidance if desired |
| Dashboard Overview | Reservation status chart | Done | Chart.js doughnut chart uses reservation status counts | Add drill-down links later |
| Dashboard Overview | Room status chart | Done | Chart.js doughnut chart uses room status counts | Add click-through to filtered room table later |
| Dashboard Overview | Payment status chart | Done | Chart.js doughnut chart uses payment status counts | Add payment date filters later |
| Dashboard Overview | Alert panel | Missing | No overdue checkout, failed payment, maintenance, or overbooking alert block yet | Add dashboard alerts |
| Rooms | Room listing table | Done | Admin room table loads live records from the database | Add search/filter controls later |
| Rooms | Room CRUD | Done | Admin can create, edit, and delete room records | Add delete guard messaging when room has reservations |
| Rooms | Bulk room type price update | Done | Admin can set one price for all rooms under a selected room type | Add audit trail if required |
| Rooms | Room type support | Done | Three room types are supported: Imperial Deluxe, Royal Executive, Emperor Presidential | Keep schema/model constants in sync if room types change |
| Rooms | Room state support | Done | Available, Reserved, Occupied, Cleaning, and Maintenance are supported | Add cleaner workflow screens later |
| Rooms | XML export | Done | `rooms.php?export=xml` exports room records | Add export links for other entities if required |
| Rooms | XML import | Done | Admin can upload room XML and create/update rooms | Add XML preview/validation report if needed |
| Reservations | Reservation listing table | Done | Admin reservation table loads live records | Add search/date filters later |
| Reservations | Reservation CRUD | Done | Admin can create, edit, and delete reservations | Add safer cancel/status action buttons |
| Reservations | Reservation validation | Done | Server-side date validation and date-overlap room availability checks exist | Add client-side helper validation later |
| Reservations | Reservation status flow | Partial | Status values exist and room status syncs on create/update/delete | Add dedicated action buttons for confirm/check-in/check-out/cancel |
| Reservations | Auto-assign best room | Missing | No automatic room matching yet | Build room matching by date, capacity, and room type |
| Check-In / Check-Out | Dedicated check-in module | Missing | Check-in/check-out can be represented through reservation status, but no focused workflow page exists | Build arrivals, check-in, and check-out workflow |
| Payments | Payment entry form | Done | Admin can record payments against reservations | Add edit/refund handling later |
| Payments | Payment history table | Done | Payment table loads live transactions | Add filters by status/date |
| Payments | Payment summary table | Done | Payment totals by status are computed from the database | Add revenue by room type report later |
| Payments | Partial payment support | Missing | No balance tracking yet | Add total paid, balance due, and payment status rules |
| Guests | Guest creation | Done | Guest records are created/upserted through reservations and user booking | Add standalone guest management page later |
| Guests | Guest profile/history | Missing | No dedicated guest detail/history screen yet | Add guest profile and stay history |
| Guests | Guest search | Missing | No standalone guest search page yet | Add search by name, phone, or email |
| Authentication | Login page | Done | Login authenticates against the users table and starts a session | Add forgot-password only if required |
| Authentication | Registration page | Done | Registration creates user accounts securely | Add email verification only if required |
| Authentication | Role-based access | Done | Admin pages require admin role; user dashboard requires login | Add more roles only if the project needs staff/cashier separation |
| Backend | Database schema | Done | `schema.sql` defines five connected tables and room seed data | Add migrations if schema changes become frequent |
| Backend | Existing database seed/update | Done | `seed_rooms.sql` updates existing database room types and seeds 36 rooms safely | Keep seed file aligned with room inventory decisions |
| Backend | Database connection | Done | PDO connection helper exists in `app/config/database.php` | Move credentials to `.env` later if needed |
| Backend | OOP models | Done | User, Guest, Room, Reservation, and Payment models exist | Keep business logic in models rather than pages |
| Backend | Form processing | Done | Admin/user forms submit to PHP handlers | Add CSRF tokens later for stronger security |
| Backend | Validation and error handling | Partial | Server-side validation and flash messages exist | Add broader validation for every field and friendlier errors |
| Reports | Occupancy report | Missing | No dedicated occupancy report page yet | Build daily/weekly/monthly occupancy report |
| Reports | Revenue report | Partial | Dashboard and payments show revenue summaries | Add standalone revenue report by date and room type |
| Reports | Reservation trend report | Partial | Dashboard chart shows monthly booking trend | Add filterable report page later |

## Suggested Next Build Order

1. Add reservation status action buttons for confirm, check-in, check-out, and cancel.
2. Build a dedicated check-in/check-out workflow page.
3. Add partial payment and balance tracking.
4. Add guest search and guest history.
5. Add report pages for occupancy, revenue, and booking trends.
6. Add CSRF protection and broader form validation.
