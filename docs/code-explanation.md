# Code Explanation Guide

This document explains how the Emperor Hotel Reservation and Management System code works from the folder structure down to the main PHP flows. It is written as a defense and walkthrough guide, so it focuses on how each part of the project connects.

## 1. Big Picture

The project is a Core PHP and MySQL hotel reservation and management system.

The system has three main page areas:

| Area | Folder | Purpose |
| --- | --- | --- |
| Public site | `public/site/` | Shows the homepage and rooms page to visitors. |
| Auth pages | `public/auth/` | Handles login, registration, and logout. |
| User pages | `public/user/` | Lets logged-in customers create reservations, pick rooms, choose payment mode, and view booking history. |
| Admin pages | `public/admin/` | Lets admins manage dashboard, rooms, reservations, payments, guests, reports, users, and receipts. |

The system uses OOP mainly through model classes in `app/models/`.

| Model | File | Main Responsibility |
| --- | --- | --- |
| `User` | `app/models/User.php` | Login accounts, registration, and admin user CRUD. |
| `Guest` | `app/models/Guest.php` | Guest records, guest search, and reservation history. |
| `Room` | `app/models/Room.php` | Room inventory, prices, statuses, summaries, and room XML import/export. |
| `Reservation` | `app/models/Reservation.php` | Booking records, validation, date-aware availability, room status sync, reports, and front desk actions. |
| `Payment` | `app/models/Payment.php` | Payment logs, generated references, balances, overpayment rules, and payment review. |
| `SupportAssistant` | `app/models/SupportAssistant.php` | AI support assistant with hybrid local-database-first and Gemini-fallback architecture, FAQ pattern matching with phrase-coverage scoring, step-by-step booking/reservation guides, date/month range parsing, and live context injection for both customer and admin scopes. |

The PHP pages are mostly controllers/views. They process form requests, call model methods, and render HTML. The model classes contain the SQL and most of the business rules.

## 2. Request Flow

Most pages follow this pattern:

1. Load shared setup through `public/includes/bootstrap.php`.
2. Load shared layout functions through `public/includes/layout.php` when the page needs HTML layout.
3. Require login or admin role if needed.
4. Create model objects using the PDO database connection.
5. If the request is `POST`, validate the submitted action and call the correct model method.
6. Set a flash message.
7. Redirect back to the page to prevent duplicate form submission.
8. If the request is `GET`, load data from the database.
9. Render the page.

Example from admin reservations:

```php
$db = Database::connect();
$guestModel = new Guest($db);
$roomModel = new Room($db);
$reservationModel = new Reservation($db);
$paymentModel = new Payment($db);
```

This means one page can coordinate multiple models, but each model still owns its own database logic.

## 3. Bootstrap And Shared Includes

### `public/includes/bootstrap.php`

This file prepares the app before a page runs.

It loads:

| Loaded File | Why It Is Needed |
| --- | --- |
| `app/config/database.php` | Provides the PDO database connection. |
| `app/helpers/auth.php` | Provides session, login, redirect, flash, escaping, and money formatting helpers. |
| `app/models/*.php` | Provides the OOP model classes used by pages. |

Pages include this file so they do not need to manually require every class.

### `public/includes/layout.php`

This file renders shared HTML layout.

Important functions:

| Function | Purpose |
| --- | --- |
| `renderHeader()` | Prints the common HTML head, CSS links, Bootstrap, fonts, favicon, and body class. |
| `renderAdminLayoutStart()` | Starts the admin shell with sidebar navigation and content panel. |
| `renderAdminLayoutEnd()` | Closes the admin shell and loads Bootstrap JavaScript. |
| `renderSiteLayoutStart()` | Starts the public site layout. |
| `renderSiteLayoutEnd()` | Closes the public site layout. |
| `renderFlashBlock()` | Shows success, warning, error, or info messages from the session. |

The admin pages pass their page-specific CSS into `renderAdminLayoutStart()`.

Example:

```php
renderAdminLayoutStart('Reservations', 'reservations', $currentAdmin, [
    '../assets/css/admin/reservations.css?v=20260530-modal-actions',
]);
```

This keeps global layout code shared while allowing every page to have its own CSS file.

## 4. Database Connection

The database connection is created in `app/config/database.php`.

The system uses PDO, not raw `mysqli`.

Main reason:

```text
PDO prepared statements make database queries safer and easier to reuse.
```

The model classes receive the database connection through their constructors:

```php
public function __construct(private PDO $db)
{
}
```

This is OOP because each model object stores the database connection and exposes methods related to one business area.

## 5. Authentication And Sessions

Authentication helpers live in `app/helpers/auth.php`.

Important helpers:

| Helper | Meaning |
| --- | --- |
| `loginUser($user)` | Saves the logged-in user into `$_SESSION['auth_user']`. |
| `logoutCurrentUser()` | Clears the current session. |
| `currentUser()` | Returns the logged-in user array. |
| `isLoggedIn()` | Checks if a user is logged in. |
| `requireAuth()` | Redirects to login if there is no user session. |
| `requireRole()` | Redirects if the user does not have the required role. |
| `setFlash()` | Stores a one-time message in the session. |
| `getFlashMessages()` | Reads and clears flash messages. |
| `e()` | Escapes output before printing it in HTML. |
| `formatMoney()` | Formats PHP currency values. |

Admin pages use:

```php
requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');
```

This means:

1. A user must be logged in.
2. The logged-in user must have the `admin` role.
3. A normal customer cannot open admin pages directly.

## 6. User Model

File: `app/models/User.php`

The `User` model handles application accounts.

Main uses:

| Use | Page |
| --- | --- |
| Login | `public/auth/login.php` |
| Register | `public/auth/register.php` |
| Admin user management | `public/admin/users.php` |
| Dashboard counts | `public/admin/dashboard.php` |

Important behavior:

- Passwords are hashed using PHP password functions.
- Login verifies the submitted password against the stored hash.
- Admin users can manage user accounts.
- Role checks determine whether a user is treated as `admin` or `user`.

The `users` table is for accounts. The `guests` table is for hotel guest information. A user account may create or own guest/reservation records, but they are not the same table.

## 7. Guest Model

File: `app/models/Guest.php`

The `Guest` model handles the hotel guest profile information used by reservations.

Main uses:

| Use | Page |
| --- | --- |
| Create/update guest from reservation form | `public/admin/reservations.php` |
| Create/update guest from customer booking | `public/user/dashboard.php` |
| Search guests | `public/admin/guests.php` |
| Show guest history | `public/admin/guests.php` |

The reservation forms collect a full name. The helper `splitFullName()` splits it into first name and last name before saving it to the `guests` table.

The project uses an upsert-style guest flow. If a guest already exists or is being edited, the model can update the guest details. If not, it creates a new guest record.

## 8. Room Model

File: `app/models/Room.php`

The `Room` model handles room inventory.

Main responsibilities:

- Create rooms.
- Read room records.
- Update rooms.
- Delete rooms.
- Bulk update room prices by room type.
- Count room statuses for dashboard charts.
- Export room records to XML.
- Import room records from XML.

Room statuses:

| Status | Meaning |
| --- | --- |
| Available | Room can be booked if the chosen dates are open. |
| Reserved | Room has an active reservation but the guest has not checked in. |
| Occupied | Guest is checked in. |

Room XML support is intentionally limited to rooms only.

Why only rooms?

```text
Room XML import/export is enough to demonstrate DOMDocument and file-based data exchange. Adding XML for users, guests, reservations, and payments would make the student project harder to explain and would require more security and validation rules.
```

So:

- `rooms` have XML import/export.
- `users`, `guests`, `reservations`, and `payments` use normal PHP/MySQL CRUD pages.

## 9. Reservation Model

File: `app/models/Reservation.php`

This is one of the most important model classes because reservations are the main system workflow.

Main responsibilities:

- Create reservations.
- Read reservation records.
- Update reservations.
- Delete reservations.
- Validate check-in and check-out dates.
- Prevent overlapping bookings for the same room.
- Validate guest and room records.
- Validate reservation status.
- Calculate room availability by date range.
- Apply front desk actions.
- Extend stays.
- Sync room status after reservation changes.
- Build dashboard summaries and reports.

### Date Validation

Reservation dates are validated server-side.

The important rules are:

| Rule | Reason |
| --- | --- |
| Check-in must be a valid date. | Prevents invalid text from being stored. |
| Check-out must be a valid date. | Prevents invalid text from being stored. |
| Check-out must be after check-in. | A stay needs at least one valid night. |
| Check-in cannot be in the past for new reservations. | Prevents booking old dates. |
| The same room cannot have active overlapping reservations. | Prevents double booking. |

The server-side model is the source of truth. Even if someone edits the HTML in the browser, the model should still reject invalid reservations.

### Date-Aware Room Availability

The system does not only look at the room status column. It also checks whether the selected room is already reserved for the selected dates.

Example:

```text
Room 101 can be Available in general, but unavailable for May 31 to June 2 if another active reservation already uses it during those dates.
```

This is handled by reservation availability methods such as:

| Method | Purpose |
| --- | --- |
| `roomsWithDateAvailability()` | Returns rooms with availability labels for a selected date range. |
| `roomIsAvailable()` | Checks if one room is free for a selected date range. |
| `roomHasActiveOverlap()` | Checks if another active reservation overlaps the selected dates. |
| `dateRangeIsValid()` | Checks whether two date strings form a valid stay range. |

### Front Desk Status Flow

Front desk actions are controlled through the `Reservation` model.

The UI does not decide freely which status comes next. The model provides the allowed actions based on the current status.

Examples:

| Current Status | Available Actions |
| --- | --- |
| Pending | Confirm, Check In, Cancel |
| Confirmed | Check In, Cancel |
| Checked-in | Check Out |
| Checked-out | No front desk status action |
| Cancelled | No front desk status action |

This logic is exposed through:

```php
$reservationModel->availableFrontDeskActions($reservation);
```

When an action is submitted, the page calls:

```php
$reservationModel->applyFrontDeskAction($reservationId, $action);
```

The model checks if the action is allowed before changing the status.

### Room Status Sync

When reservations change, the system also updates room statuses.

The sync is not based only on the reservation that was just changed. The `Reservation` model recalculates the room status by checking all active reservations that still belong to the room.

Room sync priority:

| Remaining Reservation State | Expected Room Status |
| --- | --- |
| At least one `Checked-in` reservation remains | Occupied |
| No checked-in reservation, but at least one `Pending` or `Confirmed` reservation remains | Reserved |
| No active reservation remains | Available |

This matters when a room has more than one non-overlapping reservation. If one reservation is deleted, cancelled, or checked out, the room should not automatically become Available if another active reservation still exists for a future date.

## 10. Payment Model

File: `app/models/Payment.php`

The `Payment` model handles transaction records.

Main responsibilities:

- Create payment records.
- Generate transaction references automatically.
- Store payment method, amount, status, and generated reference.
- Calculate confirmed and pending totals.
- Prevent active payments from exceeding the reservation total.
- Lock confirmed, failed, and refunded transaction logs from normal editing.
- Auto-confirm pending reservations when confirmed payments cover the full reservation total.
- Provide dashboard and report totals.

Payment statuses:

| Status | Meaning |
| --- | --- |
| Pending | Payment still needs review or confirmation. |
| Confirmed | Payment is accepted and counted as paid. |
| Failed | Payment did not succeed. |
| Refunded | Payment was returned. |

Transaction references are generated by the code. Users and staff should not type them manually.

Reference style:

| Prefix | Meaning |
| --- | --- |
| `PAY-` | Manual or cash payment reference. |
| `SIM-` | Simulated transaction reference. |

## 11. Admin Reservation Creation Page

File: `public/admin/reservations.php`

This page handles walk-in/admin reservation creation only.

Existing reservation records are managed in:

```text
public/admin/booking-records.php
```

It uses these models:

```php
$guestModel = new Guest($db);
$roomModel = new Room($db);
$reservationModel = new Reservation($db);
$paymentModel = new Payment($db);
```

### POST Action

The page checks the hidden `action` field to decide what to do.

| Action | What Happens |
| --- | --- |
| `create` | Creates a reservation and routes payment depending on payment mode. |

### Payment Route On Admin Reservation Creation

When an admin creates a reservation, the payment mode controls the next step:

| Payment Mode | Result |
| --- | --- |
| Cash | Creates an automatic pending cash payment reference and redirects to Booking Records. |
| Card, bank transfer, online payment, or other non-cash mode | Redirects to `public/admin/payments.php` for payment processing. |

This fits a walk-in hotel workflow:

```text
Cash can be handled at the counter. Non-cash methods need payment processing or review.
```

The form no longer asks for separate adult and child counts. Each room is presented with a simple "up to 5 people" note, and the database no longer stores separate adult/child count columns.

## 12. Admin Booking Records Page

File: `public/admin/booking-records.php`

This page handles existing reservation records after they are created.

### POST Actions

| Action | What Happens |
| --- | --- |
| `delete` | Deletes a reservation and recalculates the affected room status. |
| `confirm` | Changes a pending reservation to confirmed. |
| `check_in` | Checks the guest into the room. |
| `check_out` | Checks the guest out. |
| `cancel` | Cancels the reservation and releases the room. |
| `extend_stay` | Extends the stay if the same room is available for the added dates. |

### Manage Modal

The Booking Records table no longer shows many action buttons in one cramped column.

Instead, each row has one button:

```text
Manage
```

Clicking Manage opens a Bootstrap modal for that specific reservation.

The modal shows:

- Guest name.
- Room number and room type.
- Stay date range.
- Reservation status.
- Reservation total.
- Confirmed paid amount.
- Pending payment logs.
- Balance due.
- Front desk actions.
- Receipt and payment links.
- Extend stay form when allowed.
- Delete button in a danger zone.

This makes the table easier to scan while still keeping all actions available.

### Why The Modal Is Moved To `document.body`

The admin layout uses styled containers with visual effects like backdrop blur.

Some CSS effects can create a new containing context for fixed-position elements. When a Bootstrap modal is inside that kind of container, the modal can appear centered inside the panel instead of centered on the full browser screen.

The fix is:

```js
document.querySelectorAll(".reservation-action-modal").forEach((modal) => {
    document.body.appendChild(modal);
});
```

This moves the modal elements under the `<body>` before Bootstrap opens them, so the modal is positioned against the viewport.

The modal CSS also caps the modal height:

```css
.reservation-action-modal .modal-content {
    max-height: min(760px, calc(100vh - 6rem));
}

.reservation-action-modal .modal-body {
    overflow-y: auto;
}
```

This keeps the popup centered and lets the details scroll inside the modal if the content is tall.

## 13. User Dashboard Booking Flow

File: `public/user/dashboard.php`

The customer dashboard lets a logged-in user create a reservation.

The layout is designed so the user focuses on selecting a room manually.

Main parts:

| Part | Purpose |
| --- | --- |
| Stay details | Full name, dates, phone, the 5-person capacity note, and payment mode. |
| Room selection | Room cards grouped by room type with availability labels. |
| Room inclusions | Simple included perks based on selected room type. |
| Cost tracker | Calculates room price, nights, subtotal, and estimated total. |
| Booking history | Shows the user's past and current reservations below the booking form. |

The user must choose a room card. The system does not use best-room auto assignment anymore.

Reason:

```text
Manual room selection is easier to explain and more transparent for a reservation system.
```

The customer form also no longer asks for an adult/child split. Each room is shown as good for up to 5 people.

## 14. Room Card UI And Dynamic Availability

File: `public/includes/room_selection.php`

This include renders reusable room card UI used by both admin and user reservation forms.

It includes:

- Room filters: all, available, unavailable.
- Room cards grouped by room type.
- Green or red status dot.
- Room number badge.
- Price per night.
- A simple "Good for up to 5 people" capacity label.
- Hidden radio input for selected room.
- JavaScript that updates availability when dates change.
- Cost tracker logic.

The date-aware API endpoints are:

| Endpoint | Used By |
| --- | --- |
| `public/admin/room-availability.php` | Admin reservation form. |
| `public/user/room-availability.php` | Customer booking form. |

Both endpoints use shared helper logic from:

```text
public/includes/room_availability_api.php
```

This prevents duplicating the same availability response code.

## 15. Admin Payments Page

File: `public/admin/payments.php`

Admins use this page to record or review payments.

The page shows:

- Reservation selector.
- Payment amount input.
- Payment method.
- Currency.
- Payment status.
- Simulated transaction checkbox.
- Reservation cost summary.
- Transaction report log.

Important rules:

- Confirmed, failed, and refunded transaction logs are locked.
- Pending transactions can be reviewed.
- A new active payment cannot exceed the remaining reservation balance.
- When confirmed payments cover the reservation total, a pending reservation becomes confirmed automatically.

This means payment confirmation can drive reservation confirmation.

## 16. Customer Payment Page

File: `public/user/payment.php`

Customers are only sent here for non-cash payment routes.

Cash does not open the customer payment page. Cash creates an automatic pending payment reference that can be paid at the cashier.

Customer non-cash payments are simulated. They create payment records, but no real money is processed.

This is important to explain:

```text
The project demonstrates payment workflow logic, not real gateway integration.
```

## 17. Dashboard And Reports

### Admin Dashboard

File: `public/admin/dashboard.php`

The dashboard loads live data from models.

It shows:

- KPI cards.
- Recent reservations.
- Latest payment activity.
- Operational alerts.
- Monthly performance chart.
- Reservation status chart.
- Room status chart.
- Payment status chart.

Chart.js is stored locally in:

```text
public/assets/vendor/chartjs/chart.umd.min.js
```

This means the dashboard charts do not require Node.js and do not rely on a CDN at runtime.

### Reports Page

File: `public/admin/reports.php`

The reports page uses date filters and model query methods to show:

| Report | Purpose |
| --- | --- |
| Occupancy report | Shows room-night usage by room type. |
| Revenue report | Shows confirmed revenue by room type and payment method. |
| Reservation trend report | Shows daily active, cancelled, and total reservation counts. |

## 17. Receipt Page

File: `public/admin/receipt.php`

The receipt page is read-only and printable.

It displays:

- Guest details.
- Room details.
- Stay dates.
- Reservation total.
- Confirmed payment total.
- Pending payment total.
- Balance due.
- Transaction logs.

The receipt helps demonstrate that payments and reservations are connected.

## 18. CSS Organization

The project uses shared CSS plus page-specific CSS.

| CSS Location | Purpose |
| --- | --- |
| `public/assets/css/app.css` | Shared app layout, forms, tables, room cards, cost tracker, and common components. |
| `public/assets/css/site/` | Public home and rooms page styles. |
| `public/assets/css/auth/` | Login and registration page styles. |
| `public/assets/css/user/` | User dashboard and user payment styles. |
| `public/assets/css/admin/` | Admin page-specific styles. |

Reason for this organization:

```text
Shared styles stay in app.css. Page-only styles stay near the page group so bugs are easier to find and fixes are less likely to affect unrelated pages.
```

Example:

```text
public/admin/reservations.php
public/assets/css/admin/reservations.css
```

This pairing makes it easy to know where reservation and booking-record UI styles are located. The same admin reservation CSS also styles `public/admin/booking-records.php` because both pages share the room/reservation visual components.

## 19. Validation Summary

The project has server-side validation in models and page handlers.

Important validations:

| Area | Validation |
| --- | --- |
| Login | Email and password are required. |
| Registration | Required fields, email format, password length, duplicate email prevention. |
| Users | Valid role, valid email, password rules, duplicate email prevention. |
| Guests | Required name and valid contact fields. |
| Rooms | Valid room type, status, price, floor, and room number. |
| Reservations | Valid dates, no date overlaps, valid guest, valid room, status, and positive total. |
| Payments | Positive amount, valid method, valid status, valid reservation, and no overpayment. |
| Reports | Valid start and end date range. |

The important explanation:

```text
The browser UI helps the user, but the PHP models protect the database.
```

## 20. How To Trace A Reservation From Start To Finish

This is the easiest full-system walkthrough:

1. A user logs in through `public/auth/login.php`.
2. The login page uses `User.php` to verify credentials.
3. `loginUser()` saves the account in the session.
4. The user opens `public/user/dashboard.php`.
5. The dashboard loads room cards from `Room.php` and availability from `Reservation.php`.
6. The user chooses dates, payment mode, and a room card.
7. The form submits to the same dashboard page.
8. The page creates or updates the guest through `Guest.php`.
9. The page creates the reservation through `Reservation.php`.
10. The model validates dates, room existence, and overlapping bookings.
11. If the payment mode is cash, `Payment.php` creates a pending payment reference.
12. If the payment mode is non-cash, the user is sent to `public/user/payment.php`.
13. Admin can later open `public/admin/booking-records.php`.
14. Admin clicks Manage on the booking record.
15. Admin can confirm, check in, check out, cancel, extend stay, open payment, print receipt, or delete.
16. Admin can open `public/admin/payments.php` to review or record payment.
17. `Payment.php` updates payment totals and can auto-confirm a fully paid pending reservation.
18. Admin can print the receipt through `public/admin/receipt.php`.

## 21. How To Explain OOP In This Project

A simple explanation:

```text
The project uses OOP by grouping database and business logic into model classes. Each model class represents one major part of the system, such as User, Room, Reservation, or Payment. The PHP pages create objects from these classes and call their methods instead of writing all SQL directly inside the pages.
```

Example:

```php
$reservationModel = new Reservation($db);
$reservations = $reservationModel->all();
```

This means:

- `Reservation.php` knows how to query reservation records.
- `reservations.php` knows how to show and process the reservation creation page.
- `booking-records.php` knows how to show existing reservations and front desk actions.
- The SQL stays mostly inside the model.
- The page stays focused on user interaction.

## 22. Important Limitations

This project is not a production hotel system yet.

Current limitations:

- No real payment gateway.
- No email or SMS notifications.
- No mobile app.
- No offline standalone mode.
- No CSRF tokens yet.
- No dedicated housekeeping task assignment module.
- XML import/export is only for room records.

These limitations are acceptable for a student OOP PHP project because the system still demonstrates:

- CRUD.
- OOP models.
- MySQL relationships.
- Authentication.
- Role-based access.
- Date validation.
- Room availability.
- Payment tracking.
- Reporting.
- Chart.js dashboard visuals.
- Room XML import/export with DOMDocument.

## 23. AI Support Assistant Architecture

File: `app/models/SupportAssistant.php`

The AI support assistant uses a hybrid local-database-first strategy to answer customer and admin questions. It resolves common queries from the live MySQL database and only falls back to the Gemini API for open-ended or conversational questions.

### Decision Logic

`SupportAssistant::respond()` processes queries sequentially:

1. **Greeting Detection**: Uses word-boundary regex (`\b`) instead of naive substring matching to prevent false positives (e.g. `"history"` no longer triggers the `"hi"` greeting). Greetings combined with hotel keywords (e.g. `"hi, show me available rooms"`) bypass greeting replies and route to actual data queries.
2. **Dataset FAQ Matching**: Evaluates all predefined FAQ entries using a phrase-coverage scoring formula. The formula uses pattern length and word-coverage ratio to score matches, enabling polite or verbose queries to still match short FAQ patterns.
3. **Scoped Intent Routing**: Admin queries are checked for statistics, sales, operations, and overview keywords. Customer queries are checked for room availability, room types, room prices, hotel history, and booking guide keywords.
4. **AI Fallback**: Unmatched queries return `kind = ai`, triggering Gemini with live database context.

### Built-in Booking Guide

The assistant provides step-by-step booking instructions when customers ask `"how to book"`, `"booking guide"`, `"how to make a reservation"`, or similar queries:

- **Guest Guide**: Log in, go to User Dashboard, select stay dates, pick room card, choose payment mode (Cash or card/online), submit form, track status in Booking History.
- **Admin Guide**: Go to Reservations page, fill guest/room details, submit, go to Booking Records, click Manage, use modal actions (Confirm, Check-in, Extend Stay, Check-out, Cancel, Payment, Receipt, Delete).

### Date/Month Range Extraction

`extractDateRange()` parses temporal references from queries:

| Input | Parsed Range |
| --- | --- |
| `"june"` or `"jun"` | 2026-06-01 to 2026-06-30 |
| `"december"` or `"dec"` | 2025-12-01 to 2025-12-31 (smart year resolution) |
| `"yesterday"` | Previous day only |
| `"last 30 days"` | 30 days back from today |
| `"last 90 days"` | 90 days back from today |
| `"on 2026-05-15"` | Single explicit date |
| No date mentioned | Current month start to end |

### Live Context Injection

When Gemini is used, `composeAiContext()` queries the MySQL database and injects:

- **Customer scope**: Hotel profile (name, description, founding year, support email, support phone), available rooms with pricing, room catalog descriptions, and included perks.
- **Admin scope**: Dashboard counters (users, customers, revenue, available rooms, pending reservations, upcoming check-outs), monthly performance (last 6 months of bookings and confirmed revenue), room inventory by type, operational alerts (overdue check-outs with room/guest details), and range-specific revenue/occupancy.

### Gemini API Protocol

`public/support/api.php` structures the Gemini request:

- Uses `systemInstruction` (camelCase) instead of the deprecated `system_instruction` snake_case field.
- Formats conversation history as alternating `user` and `model` role turns.
- Merges consecutive same-role messages automatically to prevent API validation errors.
- Injects scoped system prompt based on whether the user is admin or customer.

### Widget Table Rendering

`public/assets/js/support-widget.js` renders Markdown tables in the chat:

- Normalizes Windows CRLF (`\r\n`) to LF (`\n`) before parsing.
- Groups consecutive pipe-delimited lines and validates table structure using divider regex.
- Strips leading/trailing empty cells from pipe splits instead of filtering all empty values, preserving correct column alignment.

## 24. Short Defense Script

Use this if you need to explain the code quickly:

```text
Our system is built with Core PHP, MySQL, and OOP model classes. The pages in public handle the interface and form submissions, while the model classes in app/models handle the database queries and business rules.

For example, reservation creation is handled by public/admin/reservations.php and public/user/dashboard.php, while existing booking records are handled by public/admin/booking-records.php. The validation and availability logic is still inside app/models/Reservation.php. Payments are recorded through payment pages, but references, balances, and overpayment rules are handled by app/models/Payment.php.

The admin Booking Records table uses a Manage modal so the table stays readable. The modal shows reservation details, payment totals, and actions like confirm, check in, extend stay, check out, cancel, receipt, payment, and delete.

The AI support assistant uses a hybrid approach: common questions about rooms, pricing, booking instructions, and admin statistics are answered from live database queries first. Only open-ended questions fall back to the Gemini API, which receives real-time hotel data as context so it cannot hallucinate room numbers or prices.

The system also includes room XML import/export using DOMDocument, but XML is intentionally limited to room records. Other tables use normal PHP and MySQL CRUD pages.

This makes the project a student-level OOP PHP hotel reservation and management system with clear separation between pages, models, database tables, and UI assets.
```

