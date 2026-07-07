# System Boundaries and Context

## System Name

Emperor Hotel Reservation and Management System

## System Context

The Emperor Hotel Reservation and Management System is a web-based hotel reservation and front desk management system. It is designed for a local XAMPP environment using Core PHP, MySQL, and browser-based pages.

The system supports public hotel browsing, user registration and login, customer reservations, side-by-side customer room selection, admin walk-in reservation management, date-aware room availability, check-in/check-out actions, guest search/history, payment recording, printable receipts, user management, operational alerts, dashboard/reporting pages, and an AI support widget for customer and admin questions.

## What The System Will Do

The system will provide these main functions:

| Area | Included Features |
| --- | --- |
| Public website | Shows the hotel homepage, room/suite details, room images, room prices, and simple room-type inclusions. |
| User management | Allows user registration, login, logout, role-based access, and admin user CRUD. |
| User booking | Allows logged-in users to create reservations from the user dashboard with full name, stay dates, phone/contact details, manual room selection, a simple up-to-5-people room note, room inclusions, a cost tracker, payment route selection, automatic pending cash payment references for cash, and a customer-safe simulated payment page for non-cash methods. |
| Room management | Allows admins to create, view, edit, delete, import, export, and bulk update room prices. |
| Reservation management | Allows admins to create walk-in reservations from the Reservations page, then manage existing booking records from the Booking Records tab with delete, status actions, manual room-card selection, same-room stay extension, date-aware availability checks, modal-based front desk controls, and automatic confirmation for fully paid pending reservations. |
| Guest management | Allows admins to search guests by name, phone, or email and view reservation/payment history. |
| Payment management | Allows customers to submit simulated non-cash payments for their own reservations and allows admins to generate automatic pending cash payment references for cash reservations, route card/bank/online methods to the Payments page, record payments, create simulated transactions, automatically generate transaction references, enforce overpayment rules, view cost/balance tracking, view summaries, and review transaction logs. |
| Receipt printing | Allows admins to open and print reservation receipts with guest, stay, payment, balance, and transaction details. |
| Dashboard reporting | Shows summary cards, operational alerts, recent records, Chart.js visual reports, and a dedicated Reports page for occupancy, revenue, and reservation trends. |
| AI support widget | Lets guests and admins ask database-aware questions through a Gemini-powered support panel that prefers live hotel data, tables, and report context before falling back to AI narration. |
| Room XML import/export | Allows room records to be exported and imported using XML with PHP DOMDocument. Other tables use normal PHP/MySQL CRUD pages. |
| Database storage | Stores users, guests, rooms, reservations, and payments in MySQL. |

## What The System Will Not Do

The system is not intended to support these features in the current version:

| Exclusion | Explanation |
| --- | --- |
| No mobile app version | The project is a web application only. It does not include Android, iOS, Flutter, or React Native. |
| No standalone offline mode | The browser assets are local, but the system still needs PHP, MySQL, and XAMPP running. It cannot work as a disconnected standalone app. |
| No real-time collaboration | The system does not include live multi-user editing, WebSockets, or shared real-time task lists. |
| No online payment gateway | Online payment is only a selectable internal method that routes to the simulated/manual Payments page. There is no Stripe, PayPal, GCash, Maya, or bank API integration yet. |
| No email/SMS notifications | The system does not currently send booking confirmations, payment reminders, or deadline notifications. |
| No full housekeeping workflow | The project now keeps room status simple: Available, Reserved, or Occupied. It does not include housekeeping task assignment or repair workflows. |
| No dedicated profile update page | Admins can update user records, but there is no separate self-service profile edit page for normal users yet. |
| No React/Tailwind frontend | The project uses PHP-rendered pages, Bootstrap, and custom CSS instead of React.js and Tailwind CSS. |
| No PostgreSQL database | The project uses MySQL/MariaDB through XAMPP, not PostgreSQL. |

## Technology Stack Overview

| Layer | Technology | Purpose |
| --- | --- | --- |
| Backend | Core PHP | Handles page rendering, form processing, authentication, and model logic. |
| Database | MySQL / MariaDB | Stores all hotel reservation data. |
| Database Access | PDO prepared statements | Connects PHP to MySQL safely and consistently. |
| Frontend UI | Bootstrap 5.3.3 | Provides responsive layout, forms, buttons, tables, alerts, and carousel behavior. |
| Icons | Bootstrap Icons 1.11.3 | Provides icons for admin navigation and UI indicators. |
| Charts | Chart.js 4.5.1 | Renders admin dashboard charts. |
| AI support | Gemini API | Powers the support widget when the local database response is not enough. |
| Fonts | Local Google Fonts files | Provides DM Sans and DM Serif Display typography without runtime CDN dependency. |
| Styling | Shared and page-specific custom CSS | Keeps reusable styles in `public/assets/css/app.css` and page-only styles in grouped CSS folders. |
| XML | PHP DOMDocument | Handles room XML import and export. |
| Local Server | XAMPP | Provides Apache/PHP/MySQL development environment. |

## Major Components

| Component | Location | Responsibility |
| --- | --- | --- |
| Database config | `app/config/database.php` | Creates the PDO database connection. |
| Auth helper | `app/helpers/auth.php` | Handles sessions, redirects, login state, flash messages, escaping, and money formatting. |
| Models | `app/models/*.php` | Contains database logic for users, guests, rooms, reservations, and payments. |
| Public pages | `public/site/*.php` | Shows public hotel pages. |
| Auth pages | `public/auth/*.php` | Handles login, registration, and logout. |
| User dashboard | `public/user/dashboard.php` | Allows logged-in users to create and view reservations. |
| Admin pages | `public/admin/*.php` | Provides dashboard, rooms, reservations, payments, guest history, reports, receipts, and user management. |
| Shared layout | `public/includes/layout.php` | Renders common page header, navigation, admin shell, scripts, and page-specific CSS links. |
| Database scripts | `database/*.sql` | Creates and seeds the database. |
| Assets | `public/assets/**` | Stores shared CSS, page-specific CSS, images, local libraries, logo, and fonts. |
| Docs | `docs/*.md` | Stores project documentation. |

## Deployment Environment

The intended deployment environment for this project is local XAMPP.

Recommended local setup:

1. Place the project folder inside `xampp/htdocs/`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Import `database/schema.sql` into MySQL.
4. Open the project in the browser through `http://localhost/emperor_hotel/`.

The project can also be moved to a PHP/MySQL web host, but the database credentials in `app/config/database.php` must be updated for that server.
