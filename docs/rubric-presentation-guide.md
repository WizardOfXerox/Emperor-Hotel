# Rubric Presentation Guide

This guide explains how to present the Emperor Hotel Reservation and Management System based on the project requirements. Use it as a defense script, checklist, and code reference during the presentation.

The main idea to explain is this:

> This project is a Core PHP, OOP, MySQL hotel reservation and front desk management system. It allows guests and users to view rooms, create reservations, choose payment routes, and track bookings. Admin users manage rooms, reservations, booking records, payments, guests, reports, users, receipts, and room XML import/export.

## Quick Presentation Order

Use this order when presenting so the system feels organized and easy to follow.

1. Start with the public pages.
2. Show registration and login.
3. Show role-based redirection for User and Admin.
4. Show the user reservation flow.
5. Show the admin dashboard and management modules.
6. Show CRUD operations.
7. Show database tables and relationships.
8. Show the OOP model classes.
9. Show XML export/import for rooms.
10. End with UI, responsiveness, accessibility, and summary.

## Requirement Summary Table

| Requirement | Project Implementation | Main Files To Show |
| --- | --- | --- |
| Authentication System | Login, registration, password hashing, sessions, role-based access for Admin and User | `public/auth/login.php`, `public/auth/register.php`, `app/helpers/auth.php`, `app/models/User.php` |
| CRUD Operations | Full CRUD is implemented for rooms, users, guests, reservations/booking records, and payments | `public/admin/rooms.php`, `public/admin/users.php`, `public/admin/guests.php`, `public/admin/reservations.php`, `public/admin/payments.php` |
| Database Design & Integration | 5 main tables with primary keys, foreign keys, relationships, SQL file, and PDO prepared statements | `database/schema.sql`, `app/config/database.php`, `app/models/*.php` |
| PHP OOP Implementation | 5 model classes with constructors, private database property, validation methods, and business methods | `app/models/User.php`, `Room.php`, `Guest.php`, `Reservation.php`, `Payment.php` |
| XML + DOM Integration | Room records can be exported to XML and imported back using PHP DOMDocument | `app/models/Room.php`, `public/admin/rooms.php` |
| User Interface & Accessibility | External CSS, Bootstrap, responsive layouts, labels, navigation, buttons, forms, modals, readable status badges | `public/assets/css`, `public/includes/layout.php`, `public/site`, `public/user`, `public/admin` |
| Presentation | The system can be demonstrated from public visitor flow to user booking to admin management | This file, `docs/project-explanation-guide.md`, `docs/code-explanation.md` |

## 1. Authentication System

### What The Requirement Means

Authentication means the system must know who the user is. It should allow users to register, log in, log out, and access only the pages allowed for their role.

For this project, there are two roles:

| Role | Purpose |
| --- | --- |
| `user` | Customer account that can book rooms, pay through simulated payment, and view booking history. |
| `admin` | Hotel staff/admin account that can manage hotel operations such as rooms, reservations, payments, guests, reports, and users. |

### What The Project Implements

The project implements:

- User registration.
- User login.
- User logout.
- Password hashing.
- Password verification.
- Session storage for logged-in users.
- Session regeneration after login.
- Role-based access control.
- Redirects for users who access pages they are not allowed to open.
- Flash messages for warnings, success messages, and errors.

### Main Files To Show

| File | Purpose |
| --- | --- |
| `public/auth/register.php` | Handles account registration form and creates a new user account. |
| `public/auth/login.php` | Handles login form and authenticates users. |
| `public/auth/logout.php` | Clears the session and logs out the current user. |
| `app/helpers/auth.php` | Contains session helpers, login/logout functions, role guards, escaping, and flash messages. |
| `app/models/User.php` | Contains database methods for user creation, lookup, authentication, update, delete, and validation. |

### Code Concepts To Explain

#### Registration

In `public/auth/register.php`, the registration page collects:

- Full name.
- Email.
- Password.
- Confirm password.
- Role, only when allowed by the system.

The page calls the `User` model to create an account. The actual database insert is handled in `app/models/User.php`.

Important point:

> The page is responsible for receiving the form request, while the `User` model is responsible for validating and saving the user record.

#### Password Hashing

In `app/models/User.php`, the password is not saved as plain text. It is saved using:

```php
password_hash($data['password'], PASSWORD_DEFAULT)
```

How to explain:

> Instead of storing the real password, the system stores a hashed version. This is safer because even if someone sees the database, they cannot directly read the original password.

#### Login Verification

The `authenticate()` method in `app/models/User.php` checks the email first, then verifies the password using:

```php
password_verify($password, $user['password_hash'])
```

How to explain:

> During login, the system does not compare plain text passwords. It compares the submitted password against the stored password hash using PHP's built-in password verification function.

#### Session Handling

In `app/helpers/auth.php`, the system starts a PHP session if one is not already active:

```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

After a successful login, the system stores only important user details in the session:

- `user_id`
- `full_name`
- `email`
- `role`

The project also calls:

```php
session_regenerate_id(true);
```

How to explain:

> The session stores the currently logged-in user. The system regenerates the session ID during login to reduce session fixation risk.

#### Role-Based Access

Admin pages use:

```php
requireAuth('../auth/login.php');
requireRole('admin', '../user/dashboard.php');
```

User pages use:

```php
requireAuth('../auth/login.php');
requireRole('user', '../admin/dashboard.php');
```

How to explain:

> `requireAuth()` checks if the visitor is logged in. `requireRole()` checks whether the logged-in account has the correct role. If not, the system redirects the user to the correct area or login page.

### How To Demonstrate Authentication

1. Open the home page.
2. Click Register.
3. Create a user account.
4. Log out.
5. Log in using the new account.
6. Show that a normal user opens the user dashboard.
7. Log in as an admin.
8. Show that admin pages are available only to admin users.
9. Try opening an admin page as a normal user and explain that the system redirects because of `requireRole()`.

### Short Defense Script

> Our authentication system uses PHP sessions. Registration creates a user record through the `User` model. Passwords are hashed using `password_hash()`, and login verifies them using `password_verify()`. After login, only safe user details such as ID, name, email, and role are stored in the session. Role-based access is handled by helper functions like `requireAuth()` and `requireRole()`, so users and admins cannot access each other's pages.

### Important Limitation To Mention Honestly

This project does not use email verification, password reset by email, two-factor authentication, or production-level account recovery. For a student project, the important authentication concepts are implemented: registration, login, logout, sessions, hashed passwords, validation, and role checks.

## 2. CRUD Operations

### What The Requirement Means

CRUD means:

| Letter | Meaning | Example |
| --- | --- | --- |
| C | Create | Add a new room, user, guest, reservation, or payment. |
| R | Read | View records in tables, dashboards, histories, reports, and details pages. |
| U | Update | Edit a room, update guest data, confirm payments, extend reservations, or change user details. |
| D | Delete | Delete records such as rooms, users, guests, or reservations. |

The requirement asks for at least 3 main entities with full CRUD.

### Main Entities With CRUD

The project has more than 3 entities with CRUD-like management.

| Entity | Create | Read | Update | Delete | Main Files |
| --- | --- | --- | --- | --- | --- |
| Rooms | Admin can create room records | Admin can view room list and public users can view rooms | Admin can edit room details and update type prices | Admin can delete rooms | `public/admin/rooms.php`, `app/models/Room.php` |
| Users | Admin can create users and registration creates users | Admin can view users | Admin can edit user details and role | Admin can delete users | `public/admin/users.php`, `app/models/User.php` |
| Guests | Admin and reservations create guest records | Admin can search/view guest records and history | Admin can update guest details | Admin can delete guests | `public/admin/guests.php`, `app/models/Guest.php` |
| Reservations | User/admin can create reservations | User sees booking history, admin sees booking records | Admin can confirm, check in, check out, cancel, extend stay | Admin can delete reservation records | `public/user/dashboard.php`, `public/admin/reservations.php`, `public/admin/booking-records.php`, `app/models/Reservation.php` |
| Payments | User/admin can create payment logs | Admin can view transaction report log and receipts | Admin can review pending payments | Payments are deleted by cascade when reservation is deleted | `public/user/payment.php`, `public/admin/payments.php`, `app/models/Payment.php` |

### CRUD Example 1: Rooms

Rooms are the clearest CRUD example.

#### Create

Admin creates a room in `public/admin/rooms.php`. The form sends:

- Room number.
- Room type.
- Floor.
- Price per night.
- Status.

The page calls:

```php
$roomModel->create($_POST);
```

The model file is:

```text
app/models/Room.php
```

#### Read

The room records are retrieved by:

```php
$rooms = $roomModel->all();
```

They are displayed in the room records table on the admin Rooms page.

Rooms are also displayed to customers on:

```text
public/site/rooms.php
public/user/dashboard.php
```

#### Update

The admin can edit a specific room and submit changes. The page calls:

```php
$roomModel->update((int) ($_POST['room_id'] ?? 0), $_POST);
```

Admins can also update all rooms of one type by changing the type price:

```php
$roomModel->updateTypePrice($roomType, $pricePerNight);
```

#### Delete

The admin can delete a room by calling:

```php
$roomModel->delete((int) ($_POST['room_id'] ?? 0));
```

### CRUD Example 2: Users

The `users` table supports customer and admin accounts.

#### Create

Users are created from:

- `public/auth/register.php`
- `public/admin/users.php`

The model method is:

```php
User::create()
```

#### Read

Admin can view all users through:

```php
User::all()
```

#### Update

Admin can edit a user through:

```php
User::update()
```

This includes full name, email, role, and optional password update.

#### Delete

Admin can delete a user through:

```php
User::delete()
```

### CRUD Example 3: Guests

Guest records store hotel guest details separately from login accounts.

This matters because a walk-in reservation may have a guest record even if the guest does not have a login account.

#### Create

Guest records can be created when:

- A user creates a reservation.
- An admin creates a walk-in reservation.
- An admin manually creates a guest.

The model method is:

```php
Guest::create()
```

#### Read

Guests can be searched and viewed in:

```text
public/admin/guests.php
```

Guest reservation history is retrieved by:

```php
Guest::reservationHistory()
```

#### Update

Admin can update guest details through:

```php
Guest::update()
```

#### Delete

Admin can delete guest records through:

```php
Guest::delete()
```

### CRUD Example 4: Reservations

Reservations are the core of the system.

#### Create

Reservations can be created from:

- Customer dashboard: `public/user/dashboard.php`
- Admin reservation page: `public/admin/reservations.php`

The model method is:

```php
Reservation::createAndGetId()
```

#### Read

Reservations are read in:

- User booking history.
- Admin booking records.
- Admin dashboard latest reservations.
- Reports.
- Receipts.

#### Update

Reservations can be updated through:

- Status changes.
- Check-in.
- Check-out.
- Cancellation.
- Stay extension.

Important model methods:

```php
Reservation::update()
Reservation::updateStatus()
Reservation::applyFrontDeskAction()
Reservation::extendStay()
```

#### Delete

Admin can delete reservations in:

```text
public/admin/booking-records.php
```

The model method is:

```php
Reservation::delete()
```

### CRUD Example 5: Payments

Payments are handled as transaction logs.

#### Create

Payment logs are created when:

- A customer chooses cash and gets a pending cashier reference.
- A customer submits a simulated online payment.
- An admin records a payment from the Payments page.

#### Read

Payment logs are shown in:

- Admin Payments page.
- Receipts.
- Dashboard payment summary.
- Booking history payment status.

#### Update

Admin can update pending payments to:

- Pending.
- Confirmed.
- Failed.
- Refunded.

Confirmed payment logs are locked after confirmation so they cannot be edited repeatedly.

#### Delete

Payment logs are not normally deleted directly from the UI. They are removed when the related reservation is deleted because the database uses:

```sql
FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE
```

How to explain:

> Payments are treated like transaction history, so the system avoids normal manual deletion. This protects the audit trail. If the reservation itself is deleted, related payments are removed by the database relationship.

### How To Demonstrate CRUD

Use rooms as the main CRUD demo because it is easy to show:

1. Go to Admin > Rooms.
2. Create a room.
3. Show it appears in the room table.
4. Edit the room.
5. Show the updated values.
6. Delete the room.
7. Explain that the actions call `Room.php`.

Then quickly show users and guests:

1. Go to Admin > Users.
2. Show create/edit/delete.
3. Go to Admin > Guests.
4. Show guest search/history and edit/delete.

Finally show reservations:

1. Create a reservation.
2. Manage it in Booking Records.
3. Change its status.
4. Delete if needed.

### Short Defense Script

> The system demonstrates CRUD in multiple entities. Rooms, users, guests, reservations, and payments are all managed through PHP pages and model classes. The page receives the form request, then the model class performs the database operation using PDO. For example, the Rooms page calls methods like `create()`, `all()`, `update()`, and `delete()` from the `Room` model.

## 3. Database Design And Integration

### What The Requirement Means

The system must have:

- At least 5 tables.
- Primary keys.
- Foreign keys.
- Relationships.
- SQL file.
- ERD.
- Proper database access using prepared statements or ORM.

This project uses PDO prepared statements, not an ORM.

### Database File

The SQL file is:

```text
database/schema.sql
```

The database documentation is:

```text
docs/database-erd.md
docs/database-usage.md
docs/erd-file-correlation.md
```

### Database Name

The database is:

```sql
emperors_hotel_db
```

### Main Tables

| Table | Purpose |
| --- | --- |
| `users` | Stores login accounts for admins and users. |
| `guests` | Stores guest/customer information used in reservations. |
| `rooms` | Stores hotel room inventory, type, floor, price, and status. |
| `reservations` | Stores room booking records, date range, total amount, and reservation status. |
| `payments` | Stores payment logs, transaction references, method, status, and amount. |

### Primary Keys

| Table | Primary Key |
| --- | --- |
| `users` | `user_id` |
| `guests` | `guest_id` |
| `rooms` | `room_id` |
| `reservations` | `reservation_id` |
| `payments` | `payment_id` |

How to explain:

> Each table has its own auto-increment primary key. This gives every record a unique identifier.

### Foreign Keys

| Relationship | Foreign Key | Meaning |
| --- | --- | --- |
| User to Guest | `guests.user_id -> users.user_id` | A guest can be linked to a registered user account. |
| User to Reservation | `reservations.user_id -> users.user_id` | A reservation can be linked to a registered user. |
| Guest to Reservation | `reservations.guest_id -> guests.guest_id` | Every reservation belongs to a guest record. |
| Room to Reservation | `reservations.room_id -> rooms.room_id` | Every reservation uses one room. |
| Reservation to Payment | `payments.reservation_id -> reservations.reservation_id` | Payments are connected to a reservation. |

### Relationship Explanation

#### Users And Guests

A user is an account that can log in. A guest is the actual hotel customer information used in the reservation.

Why they are separate:

> In a hotel system, not every guest needs a login account. A walk-in customer can have a guest record even without being a registered user. This is why `users` and `guests` are separate tables.

#### Rooms And Reservations

One room can have many reservations over time, but only one active reservation for the same date range.

The system checks date availability before creating or extending a reservation.

Important method:

```php
Reservation::roomIsAvailable()
```

#### Reservations And Payments

One reservation can have multiple payment logs.

This allows:

- Pending payment references.
- Simulated online payment review.
- Partial payments.
- Confirmed payment logs.
- Receipts with payment history.

### Database Integration

The database connection is handled in:

```text
app/config/database.php
```

The models receive the PDO database object through their constructors:

```php
public function __construct(private PDO $db)
{
}
```

How to explain:

> The database connection is centralized in `app/config/database.php`. Each model receives the PDO connection through its constructor, so the model can query the database without creating duplicate connection code.

### Prepared Statements

The project uses PDO prepared statements for inserts, updates, deletes, and many select queries.

Example from `User.php`:

```php
$statement = $this->db->prepare(
    'INSERT INTO users (full_name, email, password_hash, role) VALUES (:full_name, :email, :password_hash, :role)'
);
```

Example from `Reservation.php`:

```php
$statement = $this->db->prepare($sql);
$statement->execute($params);
```

How to explain:

> Prepared statements separate SQL structure from user-provided values. This improves safety and helps prevent SQL injection.

### ERD Explanation

Use this simple explanation:

> The ERD starts with users and guests. Users represent login accounts, while guests represent hotel customer details. Guests create reservations, reservations connect to rooms, and payments connect to reservations. This creates a complete flow from account or walk-in guest, to booking, to payment, to receipt.

### How To Demonstrate Database Design

1. Open `database/schema.sql`.
2. Point out the 5 tables.
3. Point out the primary keys.
4. Point out foreign keys in `reservations`.
5. Point out `payments.reservation_id`.
6. Open `docs/database-erd.md`.
7. Explain the relationship diagram.
8. Open a model file and show prepared statements.

### Short Defense Script

> The database has 5 main tables: users, guests, rooms, reservations, and payments. Each table has a primary key, and the reservation flow is connected through foreign keys. Reservations connect users, guests, and rooms, while payments connect to reservations. The project uses PDO prepared statements in the model classes instead of directly placing raw user input into SQL queries.

## 4. PHP OOP Implementation

### What The Requirement Means

The project must show object-oriented PHP, including:

- At least 3 classes.
- Constructors.
- Encapsulation.
- Methods.
- Separation of files.
- Use of `include` or `require`.
- Understanding of OOP principles.

### OOP Classes In The Project

The project has 5 main model classes:

| Class | File | Main Responsibility |
| --- | --- | --- |
| `User` | `app/models/User.php` | User account CRUD, authentication, password validation, email validation. |
| `Guest` | `app/models/Guest.php` | Guest CRUD, guest search, guest history, guest creation from reservation details. |
| `Room` | `app/models/Room.php` | Room CRUD, room status summaries, room type summaries, price updates, XML import/export. |
| `Reservation` | `app/models/Reservation.php` | Reservation creation, validation, availability checks, status changes, extension, reports. |
| `Payment` | `app/models/Payment.php` | Payment logs, references, totals, review status, dashboard payment data. |

### Constructors

Each model class receives a PDO object through its constructor.

Example:

```php
public function __construct(private PDO $db)
{
}
```

How to explain:

> This constructor gives each object access to the database. Instead of writing connection code in every method, the object receives the database connection once and reuses it.

### Encapsulation

Encapsulation means the class keeps its data and helper logic inside the class.

Examples in this project:

- The database connection is stored as a private property.
- Validation methods are private when they are only used inside the class.
- Database operations are grouped inside the correct model.

Examples:

| Class | Private/Internal Logic |
| --- | --- |
| `User` | `validateEmail()`, `validatePassword()`, `assertEmailIsAvailable()` |
| `Room` | `assertRoomType()`, `assertRoomStatus()`, `validateRoomNumbers()`, `nodeText()` |
| `Reservation` | `syncRoomStatus()`, `roomHasActiveOverlap()`, `validateDates()`, `assertStatus()` |

How to explain:

> Validation and database rules are not scattered everywhere. They are placed inside the model that owns the data. For example, room type validation belongs inside the `Room` class, while date and room availability validation belongs inside the `Reservation` class.

### Methods

Each class has methods that represent actions.

Example methods:

| Method | Meaning |
| --- | --- |
| `create()` | Adds a record. |
| `all()` | Reads all records. |
| `find()` | Finds one record by ID. |
| `update()` | Updates a record. |
| `delete()` | Deletes a record. |
| `authenticate()` | Checks login credentials. |
| `roomIsAvailable()` | Checks if a room is free for a date range. |
| `exportToXml()` | Converts room records into XML. |
| `importFromXml()` | Reads room records from XML and saves them. |

### Separation Of Files

The project separates responsibilities into folders:

```text
app/
  config/
    database.php
  helpers/
    auth.php
  models/
    User.php
    Guest.php
    Room.php
    Reservation.php
    Payment.php

public/
  admin/
  auth/
  includes/
  site/
  user/
  assets/

database/
  schema.sql
  seed_rooms.sql

docs/
```

How to explain:

> The model classes are separated inside `app/models`. Shared helper functions are inside `app/helpers`. Database configuration is inside `app/config`. Public web pages are inside `public`. This keeps the project more organized than placing all code in one folder.

### Include And Require

The public PHP pages load shared setup files using `require_once`.

Example:

```php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
```

The bootstrap file loads:

- Database configuration.
- Authentication helpers.
- Model autoloader.

Important file:

```text
public/includes/bootstrap.php
```

How to explain:

> `bootstrap.php` is the common starting point. It sets the timezone, defines root paths, loads database and auth helpers, and registers an autoloader for model classes.

### Autoloading

The project uses `spl_autoload_register()` to automatically load model files.

How to explain:

> When the code creates `new Room($db)`, PHP can automatically include `app/models/Room.php`. This avoids writing `require_once` for every model class on every page.

### Is This Strict MVC?

Answer honestly:

> The project is OOP and MVC-like, but it is not a strict MVC framework. The model classes are separated properly in `app/models`, while the public PHP pages act as page controllers and views. For a student Core PHP project, this is easier to explain because each page handles its own request and then calls the correct model.

### How To Demonstrate OOP

1. Open `app/models/User.php`.
2. Show the class name.
3. Show the constructor.
4. Show private validation methods.
5. Show `create()`, `authenticate()`, `update()`, `delete()`.
6. Open `app/models/Reservation.php`.
7. Show availability logic and status methods.
8. Open `public/includes/bootstrap.php`.
9. Show the autoloader.

### Short Defense Script

> The project uses OOP through model classes. Each main table has a related model class such as `User`, `Room`, `Guest`, `Reservation`, and `Payment`. These classes have constructors that receive the PDO connection, methods for CRUD and business logic, and private helper methods for validation. Shared setup is loaded through `bootstrap.php`, and model files are loaded automatically through an autoloader.

## 5. XML + DOM Integration

### What The Requirement Means

The system must demonstrate XML usage with DOM parsing. This means:

- Export records into XML format.
- Import XML records back into the database.
- Use PHP DOM classes such as `DOMDocument`.
- Follow a clear XML structure.

### What The Project Implements

The project implements XML import/export for room records.

Main files:

| File | Purpose |
| --- | --- |
| `public/admin/rooms.php` | Provides the export button and import upload form. |
| `app/models/Room.php` | Contains `exportToXml()` and `importFromXml()`. |

Important note:

> XML is intentionally limited to room records. Users, guests, reservations, and payments are managed through normal PHP/MySQL CRUD pages.

This is a good design choice for a student project because it demonstrates XML without making the system harder to explain.

### XML Export Flow

The export button is on:

```text
public/admin/rooms.php
```

The button URL is:

```text
rooms.php?export=xml
```

When this URL is opened, `rooms.php` sends XML headers:

```php
header('Content-Type: application/xml; charset=UTF-8');
header('Content-Disposition: attachment; filename="rooms-export.xml"');
echo $roomModel->exportToXml();
exit;
```

Then the `Room` model creates the XML.

### XML Export Function

The function is:

```text
app/models/Room.php
```

Method:

```php
exportToXml()
```

What it does:

1. Creates a new `DOMDocument`.
2. Sets XML version to `1.0`.
3. Sets encoding to `UTF-8`.
4. Creates a root `<rooms>` element.
5. Loops through every room record.
6. Creates a `<room>` element for each room.
7. Adds fields as child nodes.
8. Returns the generated XML string.

### XML Import Flow

The import form is also on:

```text
public/admin/rooms.php
```

The form uses:

```html
enctype="multipart/form-data"
```

This is required for file uploads.

When an admin uploads an XML file, the page checks:

```php
if (!isset($_FILES['xml_file']) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK) {
    throw new RuntimeException('Please upload a valid XML file.');
}
```

Then it calls:

```php
$result = $roomModel->importFromXml($_FILES['xml_file']['tmp_name']);
```

### XML Import Function

The function is:

```php
importFromXml(string $filePath): array
```

What it does:

1. Loads the uploaded XML file using `DOMDocument`.
2. Reads every `<room>` node.
3. Gets each room field using `nodeText()`.
4. Checks if a room number already exists.
5. Updates the existing room if the room number exists.
6. Creates a new room if the room number does not exist.
7. Returns how many records were created and updated.

### Expected XML Structure

The XML structure looks like this:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<rooms>
  <room>
    <room_number>101</room_number>
    <room_type>Imperial Deluxe</room_type>
    <floor>1</floor>
    <price_per_night>4500.00</price_per_night>
    <status>Available</status>
  </room>
  <room>
    <room_number>201</room_number>
    <room_type>Royal Executive</room_type>
    <floor>2</floor>
    <price_per_night>7500.00</price_per_night>
    <status>Available</status>
  </room>
</rooms>
```

### Valid Room Types

The room type must match one of the allowed room types:

| Room Type |
| --- |
| `Imperial Deluxe` |
| `Royal Executive` |
| `Emperor Presidential` |

### Valid Room Status Values

The room status must match one of the allowed room statuses:

| Status | Meaning |
| --- | --- |
| `Available` | The room is currently available. |
| `Reserved` | The room has an active reservation. |
| `Occupied` | The room is currently checked in. |

### How To Demonstrate XML

1. Log in as admin.
2. Go to Rooms.
3. Scroll to the XML + DOM section.
4. Click Export Rooms to XML.
5. Explain that the file is generated by `Room::exportToXml()`.
6. Open or describe the downloaded `rooms-export.xml`.
7. Edit one room price or add a new room in XML if needed.
8. Upload the XML file using Import XML File.
9. Explain that `Room::importFromXml()` reads the file with `DOMDocument`.
10. Show that the room record was created or updated in the room table.

### Short Defense Script

> XML integration is implemented for room records. In the Rooms admin page, the admin can export rooms to an XML file or import an XML file. The `Room` model uses PHP `DOMDocument` to create the XML document and to parse uploaded XML. During import, the system checks the room number. If the room already exists, it updates the room. If it does not exist, it creates a new room.

### Important Limitation To Mention Honestly

XML is only implemented for rooms. This is enough to demonstrate XML export/import and DOM parsing. The other records use normal database CRUD because adding XML for every table would make the project more complicated and less practical for a student-level hotel reservation system.

## 6. User Interface And Accessibility

### What The Requirement Means

The system should be usable and readable. It should have:

- Clean layout.
- External CSS.
- Responsive design.
- Proper form labels.
- Good alignment.
- Intuitive navigation.
- Buttons and links that are easy to understand.
- Bootstrap is allowed.

### What The Project Implements

The project has separate UI sections:

| Area | Folder |
| --- | --- |
| Public hotel website | `public/site` |
| Authentication pages | `public/auth` |
| Customer dashboard | `public/user` |
| Admin dashboard and modules | `public/admin` |
| Shared layout | `public/includes/layout.php` |
| CSS files | `public/assets/css` |

### External CSS

The project uses external CSS files instead of placing all styling directly in HTML.

Important CSS folders:

```text
public/assets/css/app.css
public/assets/css/site/
public/assets/css/auth/
public/assets/css/user/
public/assets/css/admin/
```

How to explain:

> `app.css` contains shared styles used across multiple pages. Page-specific CSS files are placed in their own folders so debugging and editing is easier.

### Bootstrap And Local Assets

The project uses Bootstrap locally.

Important files:

```text
public/assets/vendor/bootstrap/css/bootstrap.min.css
public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js
public/assets/vendor/bootstrap-icons/bootstrap-icons.css
```

How to explain:

> Bootstrap is included locally instead of relying on CDN links. This helps the project work even if internet access is unavailable during presentation.

### Responsive Layout

The project uses Bootstrap grid classes and custom CSS to make pages fit different screen sizes.

Examples:

- Public rooms page uses cards and responsive layout.
- User dashboard places booking form and room selection in an organized layout.
- Admin pages use tables, cards, panels, and modals.
- Booking Records uses a Manage button to avoid cramped action columns.

### Navigation

The system has different navigation for:

- Public visitors.
- Logged-in users.
- Admin users.

Examples:

- Public pages show Home, Rooms, Login, Register.
- User pages show dashboard and booking actions.
- Admin pages show Dashboard, Rooms, Reservations, Booking Records, Payments, Guests, Reports, Users.

How to explain:

> The navigation changes depending on the role, which makes the system easier to use because users only see the areas relevant to them.

### Accessibility Points

The project includes several accessibility-friendly practices:

- Form inputs use labels.
- Buttons have readable text.
- Tables use table headers.
- Modals have titles and close buttons.
- Statuses use visible text, not only color.
- External CSS improves consistency.
- The UI keeps important actions grouped by context.

Example:

The Booking Records page uses a Manage button that opens a modal. This prevents the table from becoming too crowded.

How to explain:

> Instead of putting every action button in one cramped table column, the system opens a modal with grouped actions such as front desk actions, receipt, payment, extension, and delete. This improves usability.

### Visual Design Direction

The project uses a hotel-themed dark and gold design:

- Dark backgrounds.
- Gold accent buttons.
- Room cards.
- Badges for status.
- Dashboard charts.
- Hotel imagery.

How to explain:

> The visual style matches the Emperor Hotel theme. Gold is used for main actions and status highlights, while dark panels make the dashboard and booking pages feel consistent.

### How To Demonstrate UI

1. Show the home page.
2. Show the rooms page.
3. Show login/register forms.
4. Show user dashboard reservation form.
5. Show room cards updating by date.
6. Show admin dashboard.
7. Show Booking Records modal.
8. Show Payments page.
9. Resize the browser or mention Bootstrap responsiveness.

### Short Defense Script

> The UI uses external CSS files, Bootstrap, local assets, organized navigation, and responsive layouts. Each major page has its own CSS file, while common styles are stored in `app.css`. Forms have labels, tables have headers, statuses use text badges, and admin actions are grouped into modals for better usability.

## 7. Presentation

### What The Requirement Means

The presentation should be organized. It should show that the system works and that the developers understand the code.

The presentation should not only show the UI. It should also explain:

- Authentication.
- CRUD.
- Database relationships.
- OOP structure.
- XML integration.
- User interface.
- System flow.

### Recommended Presentation Flow

Use this flow during the actual defense:

1. Introduce the system.
2. Explain the problem it solves.
3. Show the public pages.
4. Show registration and login.
5. Show customer reservation.
6. Show payment route.
7. Show admin dashboard.
8. Show CRUD modules.
9. Show booking records and front desk actions.
10. Show database ERD.
11. Show OOP model files.
12. Show XML import/export.
13. End with summary and limitations.

### Opening Script

> Good day. Our project is the Emperor Hotel Reservation and Management System. It is a Core PHP and MySQL web system for hotel room reservation and front desk management. The system supports public room viewing, user registration and login, customer booking, admin-created reservations, room management, booking records, payments, guest records, reports, receipts, and XML import/export for room records.

### System Boundary Script

> The main focus of the system is hotel reservation. However, a reservation system also needs supporting management features such as room inventory, payment logs, guest records, check-in and check-out, and receipts. That is why the project includes both reservation and management modules.

### Authentication Demo Script

> First, I will show the authentication system. Users can register and log in. Passwords are hashed before they are stored. After login, the system stores the user session and checks the role. Admin users go to the admin dashboard, while normal users go to the user dashboard.

### User Reservation Demo Script

> From the user dashboard, the customer can select check-in and check-out dates. The room cards update based on date availability. The customer selects a room, sees the cost tracker, chooses a payment mode, and submits the reservation. If cash is selected, the system creates a pending cashier reference. If online payment is selected, the user continues to the payment page.

### Admin Flow Demo Script

> On the admin side, the admin can manage hotel operations. The dashboard shows room availability, payment status, latest reservations, and reports. The admin can create walk-in reservations, manage booking records, check guests in and out, extend stays, record payments, print receipts, and manage room/user/guest records.

### CRUD Demo Script

> For CRUD, I will use Rooms as the main example. The admin can create a room, view it in the room table, edit its details, and delete it. The same CRUD pattern is also used for users and guests. Reservation and payment records also have create, read, and update flows, with reservation deletion available in booking records.

### Database Demo Script

> The database contains 5 main tables: users, guests, rooms, reservations, and payments. Each table has a primary key. Reservations connect users, guests, and rooms. Payments connect to reservations. These relationships are shown in the ERD and implemented in `database/schema.sql` using foreign keys.

### OOP Demo Script

> The system uses PHP OOP through model classes. The main model classes are `User`, `Guest`, `Room`, `Reservation`, and `Payment`. Each model receives the PDO connection through its constructor. The models contain methods for CRUD, validation, reports, availability checks, and payment handling.

### XML Demo Script

> XML integration is implemented in the room module. The admin can export all room records to XML or import a room XML file. The `Room` model uses PHP `DOMDocument` to create and read XML. During import, the system checks the room number and either updates an existing room or creates a new one.

### UI Demo Script

> The interface uses external CSS, Bootstrap, and page-specific styles. The public site, user dashboard, and admin pages have organized navigation. Forms use labels, tables use headers, and the Booking Records page uses a modal to keep actions clean and easy to understand.

### Closing Script

> In summary, this project satisfies the main requirements by implementing authentication, role-based access, CRUD operations, a relational MySQL database with foreign keys, PHP OOP model classes, XML import/export using DOMDocument, and a responsive user interface. The project is designed as a student-level Core PHP system, so it focuses on demonstrating the required concepts clearly and practically.

## Feature Demonstration Checklist

Use this checklist before presenting.

| Demo Item | Page/File | Done |
| --- | --- | --- |
| Open public home page | `public/site/home.php` | [ ] |
| Open rooms page | `public/site/rooms.php` | [ ] |
| Register a user | `public/auth/register.php` | [ ] |
| Log in as user | `public/auth/login.php` | [ ] |
| Create a customer reservation | `public/user/dashboard.php` | [ ] |
| Show cost tracker | `public/user/dashboard.php` | [ ] |
| Show cash reference or online payment route | `public/user/dashboard.php`, `public/user/payment.php` | [ ] |
| Log in as admin | `public/auth/login.php` | [ ] |
| Show admin dashboard charts | `public/admin/dashboard.php` | [ ] |
| Create/edit/delete room | `public/admin/rooms.php` | [ ] |
| Create/edit/delete user | `public/admin/users.php` | [ ] |
| Search/edit/delete guest | `public/admin/guests.php` | [ ] |
| Create walk-in reservation | `public/admin/reservations.php` | [ ] |
| Manage booking record | `public/admin/booking-records.php` | [ ] |
| Confirm payment | `public/admin/payments.php` | [ ] |
| Print receipt | `public/admin/receipt.php` | [ ] |
| Show reports | `public/admin/reports.php` | [ ] |
| Export rooms to XML | `public/admin/rooms.php?export=xml` | [ ] |
| Import room XML | `public/admin/rooms.php` | [ ] |
| Show database schema | `database/schema.sql` | [ ] |
| Show ERD documentation | `docs/database-erd.md` | [ ] |
| Show OOP classes | `app/models/*.php` | [ ] |

## Common Questions And Answers

### Is this a reservation system or a hotel management system?

Answer:

> It is mainly a hotel reservation system, but it includes hotel management features because reservations need supporting modules. To reserve rooms properly, the system needs room inventory, guest records, payment logs, booking records, check-in/check-out, receipts, and reports.

### Why are users and guests separate?

Answer:

> Users are login accounts. Guests are hotel customer records. A registered user can be linked to a guest record, but a walk-in guest can also exist without a login account. This makes the system more flexible for a hotel front desk.

### Why is XML only for rooms?

Answer:

> XML is implemented for rooms to demonstrate XML export/import and DOMDocument. Room records are safe and simple to exchange as XML. Users, reservations, guests, and payments involve more sensitive relationships and are better managed through normal database CRUD pages.

### Is the project strict MVC?

Answer:

> It is MVC-like but not strict MVC. The models are separated in `app/models`. The public PHP pages act as page controllers and views. This keeps the project understandable for Core PHP while still demonstrating OOP and separation of responsibilities.

### Does the system prevent double booking?

Answer:

> Yes. The reservation model checks whether a room has an overlapping active reservation for the selected check-in and check-out dates. The user interface also updates room availability dynamically based on the selected dates.

### What happens when a payment is confirmed?

Answer:

> Confirmed payments reduce the reservation balance. Once a reservation is fully paid, the reservation can become confirmed. Confirmed transaction logs are locked so they cannot be repeatedly edited.

### Why use prepared statements?

Answer:

> Prepared statements protect the SQL structure from user input. They reduce SQL injection risk and make database operations cleaner.

### Why use Bootstrap?

Answer:

> Bootstrap helps with responsive layout, forms, grids, modals, and buttons. The project also uses custom external CSS for the hotel theme and page-specific styling.

## Requirement By Requirement Final Defense Summary

### 1. Authentication System

The project satisfies this requirement through login, registration, logout, password hashing, sessions, and role-based access. The `User` model handles account data, while `auth.php` handles session and access helpers.

### 2. CRUD Operations

The project satisfies this requirement with CRUD for rooms, users, guests, reservations, and payments. Rooms, users, and guests are the clearest full CRUD examples.

### 3. Database Design And Integration

The project satisfies this requirement with 5 main tables, primary keys, foreign keys, ERD documentation, SQL schema, and PDO prepared statements.

### 4. PHP OOP Implementation

The project satisfies this requirement with 5 model classes: `User`, `Guest`, `Room`, `Reservation`, and `Payment`. These classes use constructors, methods, private validation helpers, and separated files.

### 5. XML + DOM Integration

The project satisfies this requirement through room XML export/import using `DOMDocument` in the `Room` model.

### 6. User Interface And Accessibility

The project satisfies this requirement through external CSS, Bootstrap, responsive pages, labels, navigation, modals, tables, and readable action buttons/status badges.

### 7. Presentation

The project can be presented clearly by following the actual flow: public pages, authentication, user reservation, admin management, CRUD, database, OOP, XML, UI, and final summary.

## Final One-Minute Summary

Use this if you need a short closing explanation:

> The Emperor Hotel Reservation and Management System is a Core PHP and MySQL project that demonstrates the required concepts through a complete hotel booking workflow. It has authentication with role-based access, CRUD modules for multiple entities, a relational database with 5 main tables, OOP model classes, XML import/export using DOMDocument, and a responsive interface using external CSS and Bootstrap. The customer side focuses on room selection, booking, and payment routing, while the admin side supports room management, walk-in reservations, booking records, payments, guests, reports, users, and receipts.
