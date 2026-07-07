# SQL Query Explanation Guide

This guide explains the SQL queries used inside the model classes of the Emperor Hotel Reservation and Management System.

The model files are located in:

```text
app/models/
```

The five model files are:

| Model | File | Main Database Table |
| --- | --- | --- |
| `User` | `app/models/User.php` | `users` |
| `Guest` | `app/models/Guest.php` | `guests` |
| `Room` | `app/models/Room.php` | `rooms` |
| `Reservation` | `app/models/Reservation.php` | `reservations` |
| `Payment` | `app/models/Payment.php` | `payments` |

## Purpose Of The Model Queries

The model classes are responsible for talking to the database. The page files in `public/` receive form submissions and display the user interface, but the actual database work is handled by model methods.

For example:

```text
public/admin/rooms.php
```

calls methods from:

```text
app/models/Room.php
```

This keeps the SQL logic centralized. Instead of writing room SQL in many pages, the room-related SQL stays inside the `Room` model.

## Prepared Statements

Most queries use PDO prepared statements.

Example:

```php
$statement = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
$statement->execute(['email' => $email]);
```

This is safer than placing user input directly into SQL.

### Why Prepared Statements Are Used

Prepared statements help because:

- The SQL structure is written separately from the user-provided values.
- Values such as email, password, room ID, date, and guest ID are passed as parameters.
- It reduces the risk of SQL injection.
- It makes the code easier to read and explain.

### Prepared Statement Explanation Script

Use this during presentation:

> The system uses PDO prepared statements for user input. The SQL query uses placeholders such as `:email` or `:reservation_id`, and the actual value is passed during `execute()`. This prevents the input from being directly mixed into the SQL string.

## Direct Queries

Some queries use:

```php
$this->db->query(...)
```

This is used when the query does not contain direct user input.

Example:

```php
SELECT COUNT(*) FROM users
```

This query is safe to run directly because no visitor-provided value is inserted into the SQL.

## Query Types Used In The Project

| Query Type | Purpose In This Project |
| --- | --- |
| `SELECT` | Reads records for tables, dashboards, reports, validation, and summaries. |
| `INSERT` | Creates users, guests, rooms, reservations, and payment logs. |
| `UPDATE` | Updates account details, rooms, reservation statuses, room statuses, and payment reviews. |
| `DELETE` | Deletes users, guests, rooms, and reservations. |
| `JOIN` | Combines related tables such as reservations with guests and rooms. |
| `GROUP BY` | Creates summaries such as counts by status, revenue by method, and reservations by date. |
| `SUM` | Calculates totals such as revenue, payment amount, and spending. |
| `COUNT` | Counts records such as users, rooms, reservations, payments, and conflicts. |
| `CASE` | Separates totals by status, such as confirmed vs pending payments. |
| `LEFT JOIN` | Keeps records visible even when related rows are missing. |
| `INNER JOIN` | Requires matching related records before showing a row. |

## User Model Queries

File:

```text
app/models/User.php
```

The `User` model handles account records, authentication, and admin user management.

### `countUsers()`

Query purpose:

```sql
SELECT COUNT(*) FROM users
```

Explanation:

This counts all user accounts. It can be used for dashboard summaries or admin information.

### `all()`

Query purpose:

```sql
SELECT user_id, full_name, email, role, created_at
FROM users
ORDER BY created_at DESC
```

Explanation:

This reads the user list for the admin Users page. It intentionally selects safe fields only and does not include `password_hash`.

### `find()`

Query purpose:

```sql
SELECT user_id, full_name, email, role, created_at
FROM users
WHERE user_id = :user_id
LIMIT 1
```

Explanation:

This finds one user by ID. The `LIMIT 1` makes it clear that only one record is expected.

### `findByEmail()`

Query purpose:

```sql
SELECT *
FROM users
WHERE email = :email
LIMIT 1
```

Explanation:

This is used during login and duplicate email checking. It needs `password_hash` during authentication, so it selects all fields.

### `create()`

Query purpose:

```sql
INSERT INTO users (full_name, email, password_hash, role)
VALUES (:full_name, :email, :password_hash, :role)
```

Explanation:

This creates a new user account. The password is already hashed before being inserted.

### `update()`

Query purpose when password is changed:

```sql
UPDATE users
SET full_name = :full_name,
    email = :email,
    role = :role,
    password_hash = :password_hash
WHERE user_id = :user_id
```

Query purpose when password is not changed:

```sql
UPDATE users
SET full_name = :full_name,
    email = :email,
    role = :role
WHERE user_id = :user_id
```

Explanation:

There are two update queries so the password hash is only replaced when the admin enters a new password.

### `delete()`

Query purpose:

```sql
DELETE FROM users
WHERE user_id = :user_id
```

Explanation:

This deletes a user account by ID.

## Guest Model Queries

File:

```text
app/models/Guest.php
```

The `Guest` model manages hotel guest profiles and guest history.

### `find()`, `findByEmail()`, And `findByUserId()`

These methods use simple `SELECT` queries to find one guest record.

Examples:

```sql
SELECT * FROM guests WHERE guest_id = :guest_id LIMIT 1
SELECT * FROM guests WHERE email = :email LIMIT 1
SELECT * FROM guests WHERE user_id = :user_id LIMIT 1
```

Explanation:

These queries are used to locate an existing guest profile before creating, updating, or linking a reservation.

### `search()`

Main query idea:

```sql
SELECT g.*,
       COALESCE(stats.reservation_count, 0) AS reservation_count,
       stats.last_stay,
       COALESCE(stats.total_spent, 0) AS total_spent
FROM guests g
LEFT JOIN (
    SELECT guest_id,
           COUNT(*) AS reservation_count,
           MAX(check_out) AS last_stay,
           SUM(total_amount) AS total_spent
    FROM reservations
    GROUP BY guest_id
) stats ON stats.guest_id = g.guest_id
WHERE ...
ORDER BY g.created_at DESC
```

Explanation:

This query lists guests and includes reservation statistics. The subquery groups reservations by guest and calculates:

- Number of reservations.
- Last check-out date.
- Total amount spent.

The `LEFT JOIN` is important because guests without reservations should still appear.

The optional `WHERE` condition searches by:

- Full name.
- Email.
- Phone.

### `reservationHistory()`

Main query idea:

```sql
SELECT r.*,
       rm.room_number,
       rm.room_type,
       COALESCE(payments.confirmed_paid, 0) AS confirmed_paid,
       COALESCE(payments.pending_paid, 0) AS pending_paid
FROM reservations r
INNER JOIN rooms rm ON rm.room_id = r.room_id
LEFT JOIN (
    SELECT reservation_id,
           SUM(CASE WHEN payment_status = 'Confirmed' THEN amount ELSE 0 END) AS confirmed_paid,
           SUM(CASE WHEN payment_status = 'Pending' THEN amount ELSE 0 END) AS pending_paid
    FROM payments
    GROUP BY reservation_id
) payments ON payments.reservation_id = r.reservation_id
WHERE r.guest_id = :guest_id
ORDER BY r.created_at DESC
```

Explanation:

This shows the reservation history for one guest. It joins reservations to rooms so the system can show the room number and room type. It also joins payment totals to show confirmed and pending payment amounts.

### `create()`

Query purpose:

```sql
INSERT INTO guests (user_id, first_name, last_name, phone, email)
VALUES (:user_id, :first_name, :last_name, :phone, :email)
```

Explanation:

This creates a guest profile. `user_id` can be `NULL` for walk-in guests.

### `update()`

Query purpose:

```sql
UPDATE guests
SET user_id = :user_id,
    first_name = :first_name,
    last_name = :last_name,
    phone = :phone,
    email = :email
WHERE guest_id = :guest_id
```

Explanation:

This updates guest contact details and optional account linkage.

### `delete()`

Query purpose:

```sql
DELETE FROM guests
WHERE guest_id = :guest_id
```

Explanation:

This deletes a guest record by ID.

## Room Model Queries

File:

```text
app/models/Room.php
```

The `Room` model manages room inventory, prices, statuses, summaries, and XML import/export.

### `all()`

Query purpose:

```sql
SELECT *
FROM rooms
ORDER BY floor ASC, room_number ASC
```

Explanation:

This displays rooms in a natural hotel order: lower floors first, then room number.

### `availableRooms()`

Query purpose:

```sql
SELECT *
FROM rooms
WHERE status = 'Available'
ORDER BY floor ASC, room_number ASC
```

Explanation:

This returns rooms whose current room status is `Available`.

Important note:

This is different from date-aware availability. Date-aware availability is handled in the `Reservation` model because it must check reservation date overlaps.

### `find()` And `findByNumber()`

Query purpose:

```sql
SELECT * FROM rooms WHERE room_id = :room_id LIMIT 1
SELECT * FROM rooms WHERE room_number = :room_number LIMIT 1
```

Explanation:

`find()` is used for editing and validation. `findByNumber()` is used during XML import so the system can decide whether to update an existing room or create a new one.

### `create()`

Query purpose:

```sql
INSERT INTO rooms (room_number, room_type, floor, price_per_night, status)
VALUES (:room_number, :room_type, :floor, :price_per_night, :status)
```

Explanation:

This creates a room after validating:

- Room number.
- Room type.
- Floor.
- Price per night.
- Status.

### `update()`

Query purpose:

```sql
UPDATE rooms
SET room_number = :room_number,
    room_type = :room_type,
    floor = :floor,
    price_per_night = :price_per_night,
    status = :status
WHERE room_id = :room_id
```

Explanation:

This updates one room record.

### `updateTypePrice()`

Query purpose:

```sql
UPDATE rooms
SET price_per_night = :price_per_night
WHERE room_type = :room_type
```

Explanation:

This bulk-updates all rooms under one room type. It allows the admin to change room rates dynamically instead of editing each room one by one.

### `delete()`

Query purpose:

```sql
DELETE FROM rooms
WHERE room_id = :room_id
```

Explanation:

This deletes one room. The database can block deletion if reservations still reference the room because `reservations.room_id` uses a foreign key.

### `statusSummary()`

Query purpose:

```sql
SELECT status, COUNT(*) AS total
FROM rooms
GROUP BY status
```

Explanation:

This counts rooms per status. The method converts this into available vs not available counts.

### `statusBreakdown()`

Query purpose:

```sql
SELECT status, COUNT(*) AS total
FROM rooms
GROUP BY status
```

Explanation:

This is used for the dashboard chart showing room counts by `Available`, `Reserved`, and `Occupied`.

### `roomsByStatus()`

Query purpose:

```sql
SELECT *
FROM rooms
WHERE status = :status
ORDER BY floor ASC, room_number ASC
LIMIT :limit
```

Explanation:

This gives a limited preview of rooms with one selected status.

### `typeSummary()`

Query purpose:

```sql
SELECT room_type,
       COUNT(*) AS total_rooms,
       SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) AS available_rooms,
       MIN(price_per_night) AS lowest_price,
       MAX(price_per_night) AS highest_price
FROM rooms
GROUP BY room_type
```

Explanation:

This groups rooms by room type and calculates:

- Total rooms per type.
- Available rooms per type.
- Lowest price per type.
- Highest price per type.

This is used for room type summaries and price management.

### XML Import And Export

XML does not use SQL directly by itself, but it calls the room methods that use SQL:

- `exportToXml()` calls `all()`.
- `importFromXml()` calls `findByNumber()`, then either `update()` or `create()`.

How to explain:

> XML export reads room records from MySQL and converts them into XML. XML import reads XML room records and saves them back to MySQL through the same model methods used by the normal room CRUD.

## Reservation Model Queries

File:

```text
app/models/Reservation.php
```

The `Reservation` model contains the most important business logic in the system.

It handles:

- Reservation creation.
- Date validation.
- Date-aware room availability.
- Reservation editing.
- Cancellation.
- Check-in.
- Check-out.
- Stay extension.
- Room status syncing.
- Operational alerts.
- Occupancy reports.
- Dashboard summaries.

### `all()`

Query purpose:

```sql
SELECT r.*, g.first_name, g.last_name, g.email AS guest_email, g.phone,
       rm.room_number, rm.room_type, u.full_name AS user_name
FROM reservations r
INNER JOIN guests g ON g.guest_id = r.guest_id
INNER JOIN rooms rm ON rm.room_id = r.room_id
LEFT JOIN users u ON u.user_id = r.user_id
ORDER BY r.created_at DESC
```

Explanation:

This lists all reservations with guest, room, and optional user account information.

`INNER JOIN` is used for guests and rooms because every reservation must have a guest and room.

`LEFT JOIN` is used for users because walk-in reservations may not have a linked user account.

### `find()`

Query purpose:

This finds one reservation with guest and room details.

It is used for:

- Receipt view.
- Edit forms.
- Booking Records modal.
- Validation before update/delete.

### `userReservations()`

Query purpose:

This lists reservations for one logged-in user.

Explanation:

It powers the customer's booking history.

### `createAndGetId()`

Query purpose:

```sql
INSERT INTO reservations (user_id, guest_id, room_id, check_in, check_out, total_amount, status)
VALUES (:user_id, :guest_id, :room_id, :check_in, :check_out, :total_amount, :status)
```

Explanation:

This creates a reservation after validation. Before inserting, the method checks:

- Dates are valid.
- Room exists.
- Guest exists.
- Status is allowed.
- Total amount is greater than zero.
- Room is available for the selected date range.

After saving, it calls `syncRoomStatus()` so the room status updates.

### `update()`

Query purpose:

This updates an existing reservation.

Important detail:

When checking availability during update, the query excludes the reservation being edited. Without this, the reservation would conflict with itself.

### `delete()`

Query purpose:

```sql
DELETE FROM reservations
WHERE reservation_id = :reservation_id
```

Explanation:

This deletes a reservation. The payments connected to the reservation are deleted automatically because the `payments` table uses `ON DELETE CASCADE`.

After deletion, the model syncs the room status again so a room does not stay stuck as Reserved or Occupied.

### `updateStatus()`

Query purpose:

```sql
UPDATE reservations
SET status = :status
WHERE reservation_id = :reservation_id
```

Explanation:

This changes the status for front desk actions such as:

- Confirm.
- Check In.
- Check Out.
- Cancel.

After the update, room status is synced.

### `extendStay()`

Query purpose:

```sql
UPDATE reservations
SET check_out = :check_out,
    total_amount = :total_amount
WHERE reservation_id = :reservation_id
```

Explanation:

This extends the check-out date and increases the total amount based on extra nights.

Before updating, the system checks whether the room is available during the extension dates.

### `roomIsAvailable()`

This is one of the most important queries in the project.

Query idea:

```sql
SELECT COUNT(*)
FROM reservations
WHERE room_id = :room_id
  AND status NOT IN ('Cancelled', 'Checked-out')
  AND NOT (check_out <= :check_in OR check_in >= :check_out)
```

Explanation:

This checks whether the selected room has an active overlapping reservation.

The room is considered unavailable if another active reservation overlaps the requested dates.

The overlap logic is:

```text
NOT (existing check-out is before/equal requested check-in
     OR existing check-in is after/equal requested check-out)
```

In simpler words:

> If the existing booking is not completely before the new booking and not completely after the new booking, then it overlaps.

Cancelled and checked-out reservations are ignored because they should not block new bookings.

### `roomsWithDateAvailability()`

Query purpose:

```sql
SELECT *
FROM rooms
ORDER BY floor ASC, room_number ASC
```

Explanation:

This first reads all rooms. Then each room is checked with `roomIsAvailable()` for the selected dates.

This is what makes the room cards dynamically show:

- Available for dates.
- Booked for dates.

### `operationalAlerts()`

This method has two alert queries.

#### Overdue Checkouts

Query purpose:

Find checked-in reservations where the check-out date is already before today.

This helps the admin notice guests who should have checked out already.

#### Overbooking Conflicts

Query purpose:

The query self-joins the `reservations` table to find overlapping active reservations for the same room.

Self-join means the table is joined to itself:

```text
reservations r1
reservations r2
```

This is used as an operational safety check.

### `occupancyReport()`

This is the most advanced reporting query.

It calculates:

- Room count per room type.
- Booked room nights.
- Available room nights.
- Occupancy rate.

Important SQL functions:

| SQL Function | Purpose |
| --- | --- |
| `DATEDIFF()` | Counts days/nights between two dates. |
| `LEAST()` | Chooses the earlier of two dates. |
| `GREATEST()` | Chooses the later of two dates. |
| `COALESCE()` | Uses `0` when no value exists. |

Why `LEAST()` and `GREATEST()` are used:

> A reservation may start before the report range or end after the report range. `LEAST()` and `GREATEST()` make sure the report only counts the part of the stay that overlaps the selected report dates.

### `reservationTrendReport()`

Query purpose:

```sql
SELECT DATE(created_at) AS reservation_date,
       COUNT(*) AS total_reservations,
       SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_reservations,
       SUM(CASE WHEN status != 'Cancelled' THEN 1 ELSE 0 END) AS active_reservations
FROM reservations
WHERE DATE(created_at) BETWEEN :start_date AND :end_date
GROUP BY DATE(created_at)
ORDER BY reservation_date ASC
```

Explanation:

This groups reservations by creation date. It separates cancelled reservations from active reservations.

### `dashboardSummary()`

This method runs three summary queries:

| Query | Purpose |
| --- | --- |
| Count distinct guests this month | Shows monthly customer count. |
| Count pending reservations | Shows how many reservations need attention. |
| Count upcoming checkouts | Shows check-outs within the next 3 days. |

### `monthlyPerformance()`

Query purpose:

Groups reservations by month and sums confirmed payment income.

This powers the monthly performance chart on the admin dashboard.

### `statusBreakdown()`

Query purpose:

Counts reservations by status.

This powers status summaries/charts.

### `recent()`

Query purpose:

Reads the newest reservations with guest and room details for dashboard activity.

### `syncRoomStatus()`

This method keeps the `rooms.status` column aligned with reservation status.

It does three things:

1. Confirms the room exists.
2. Finds the highest-priority active reservation for the room.
3. Updates the room status.

Status logic:

| Reservation Situation | Room Status |
| --- | --- |
| Active checked-in reservation exists | `Occupied` |
| Active pending or confirmed reservation exists | `Reserved` |
| No active reservation exists | `Available` |

This is important because deleting, cancelling, checking out, or editing a reservation should update the room status correctly.

## Payment Model Queries

File:

```text
app/models/Payment.php
```

The `Payment` model manages payment logs, transaction references, payment review, balances, and revenue reports.

### `createAndGetId()`

Query purpose:

```sql
INSERT INTO payments (reservation_id, amount, payment_method, payment_status, transaction_reference)
VALUES (:reservation_id, :amount, :payment_method, :payment_status, :transaction_reference)
```

Explanation:

This creates a payment log for a reservation.

Before inserting, it validates:

- Reservation exists.
- Amount is greater than zero.
- Payment method is allowed.
- Payment status is allowed.
- The payment will not overpay the reservation.

It also generates a transaction reference such as:

```text
PAY-00001-YYYYMMDDHHMMSS-ABCD
SIM-00001-YYYYMMDDHHMMSS-ABCD
```

### `all()`

Query purpose:

Lists all payment logs with reservation, guest, and room details.

This is used on the admin Payments page.

### `updateReview()`

Query purpose:

```sql
UPDATE payments
SET amount = :amount,
    payment_status = :payment_status
WHERE payment_id = :payment_id
```

Explanation:

This reviews a pending payment. It allows an admin to confirm, fail, or refund a pending transaction.

Important rule:

> Only pending payments can be reviewed. Confirmed, failed, and refunded transactions are locked.

### `syncFullyPaidReservation()`

This method checks whether confirmed payments already cover the full reservation total.

If the reservation is fully paid and still pending, it runs:

```sql
UPDATE reservations
SET status = 'Confirmed'
WHERE reservation_id = :reservation_id
```

Then it updates the room:

```sql
UPDATE rooms
SET status = 'Reserved'
WHERE room_id = :room_id
```

Explanation:

This means a fully paid pending reservation can automatically become confirmed.

### `find()`

Query purpose:

Finds one payment by `payment_id`.

This is used before payment review.

### `forReservation()`

Query purpose:

Lists all payments for one reservation.

This is used in:

- Receipts.
- Booking history.
- Payment balance calculations.

### `totalsByReservation()`

Query purpose:

Groups all payments by reservation and calculates:

- Logged amount.
- Confirmed amount.
- Pending amount.

The query uses `CASE`:

```sql
SUM(CASE WHEN payment_status = 'Confirmed' THEN amount ELSE 0 END)
SUM(CASE WHEN payment_status = 'Pending' THEN amount ELSE 0 END)
```

Explanation:

This lets the system know how much money is already confirmed and how much is still pending review.

### `totalsForReservation()`

This method calculates the balance of one reservation.

It returns:

| Field | Meaning |
| --- | --- |
| `reservation_total` | The full reservation cost. |
| `logged_amount` | Total of all payment logs. |
| `confirmed_amount` | Payments already confirmed. |
| `pending_amount` | Payments waiting for review. |
| `balance_due` | Reservation total minus confirmed amount. |
| `active_balance_due` | Reservation total minus confirmed and pending amounts. |

The optional `excludePaymentId` is used when editing a pending payment so the payment does not count against itself during overpayment validation.

### `revenueThisMonth()`

Query purpose:

Sums confirmed payments in the current month.

This is used for dashboard revenue.

### `summaryByStatus()`

Query purpose:

Groups payments by status and totals amount per status.

This powers payment charts and summaries.

### `failedPayments()`

Query purpose:

Lists recent failed payments with guest and room details.

### `revenueReport()`

This method contains three report queries:

| Query | Purpose |
| --- | --- |
| Total confirmed revenue | Shows total revenue for a date range. |
| Revenue by room type | Shows confirmed revenue grouped by room type. |
| Revenue by payment method | Shows confirmed revenue grouped by payment method. |

The room type query uses `LEFT JOIN` so all room types can appear even if there is no revenue for one type in the selected date range.

### `assertReservationExists()`

Query purpose:

```sql
SELECT COUNT(*)
FROM reservations
WHERE reservation_id = :reservation_id
```

Explanation:

This prevents saving a payment for a reservation that does not exist.

## Important SQL Concepts Used

### `INNER JOIN`

Used when the related record must exist.

Example:

Reservations must have rooms and guests, so the system uses:

```sql
INNER JOIN guests g ON g.guest_id = r.guest_id
INNER JOIN rooms rm ON rm.room_id = r.room_id
```

### `LEFT JOIN`

Used when the related record is optional.

Example:

Walk-in reservations may not have a user account, so the system uses:

```sql
LEFT JOIN users u ON u.user_id = r.user_id
```

### `COALESCE()`

Used to replace `NULL` with a default value.

Example:

```sql
COALESCE(SUM(amount), 0)
```

This prevents empty totals from displaying as `NULL`.

### `CASE`

Used for conditional totals.

Example:

```sql
SUM(CASE WHEN payment_status = 'Confirmed' THEN amount ELSE 0 END)
```

This means:

> Add the amount only if the payment is Confirmed. Otherwise, add 0.

### `GROUP BY`

Used for summaries.

Examples:

- Count rooms by status.
- Count reservations by date.
- Sum revenue by payment method.
- Sum guest spending by guest ID.

### Date Overlap Logic

This is the most important reservation SQL concept.

Two date ranges overlap when:

```text
existing check-in < requested check-out
AND
existing check-out > requested check-in
```

The project writes this as:

```sql
NOT (check_out <= :check_in OR check_in >= :check_out)
```

This means:

> If the existing reservation is not completely before or completely after the requested stay, then it overlaps.

## How To Explain SQL During Presentation

Use this simple script:

> The SQL queries are organized inside the model classes. Each model owns the queries for its table. For example, `Room.php` handles room queries, `Reservation.php` handles reservation and availability queries, and `Payment.php` handles payment totals and revenue reports. The system uses PDO prepared statements for queries that receive user input. It also uses joins to connect related tables, aggregates like `COUNT` and `SUM` for dashboard summaries, and date overlap logic to prevent double booking.

## Best Files To Show During Defense

| Topic | File To Show |
| --- | --- |
| Login query and password verification | `app/models/User.php` |
| Guest search and history | `app/models/Guest.php` |
| Room CRUD and XML import/export | `app/models/Room.php` |
| Date-aware availability | `app/models/Reservation.php` |
| Room status syncing | `app/models/Reservation.php` |
| Payment totals and overpayment rules | `app/models/Payment.php` |
| Database table relationships | `database/schema.sql` |
| ERD explanation | `docs/database-erd.md` |

## Final Summary

The model SQL queries support the full hotel reservation workflow:

1. `User.php` handles account login and user management.
2. `Guest.php` handles guest records and guest history.
3. `Room.php` handles room inventory, status summaries, pricing, and XML room import/export.
4. `Reservation.php` handles booking creation, date-aware availability, check-in/check-out, extensions, status syncing, and reports.
5. `Payment.php` handles payment logs, references, payment review, balances, and revenue reports.

Together, these queries connect the user interface to the MySQL database and show the project's database integration, CRUD operations, OOP organization, and reporting logic.
