# System Boundaries and Context

## System Name

Emperor Hotel Reservation System

## System Context

The Emperor Hotel Reservation System is a web-based hotel reservation and admin management system. It is designed for a local XAMPP environment using Core PHP, MySQL, and browser-based pages.

The system supports public hotel browsing, user registration and login, customer reservations, admin room management, admin reservation management, payment recording, user management, and dashboard reporting.

## What The System Will Do

The system will provide these main functions:

| Area | Included Features |
| --- | --- |
| Public website | Shows the hotel homepage, room/suite details, room images, room prices, and available room inventory. |
| User management | Allows user registration, login, logout, role-based access, and admin user CRUD. |
| User booking | Allows logged-in users to create reservations from the user dashboard. |
| Room management | Allows admins to create, view, edit, delete, import, export, and bulk update room prices. |
| Reservation management | Allows admins to create, view, edit, delete, and manage reservation records. |
| Payment management | Allows admins to record payments and view payment summaries. |
| Dashboard reporting | Shows summary cards, recent records, and Chart.js visual reports for bookings, rooms, reservations, and payments. |
| XML import/export | Allows room records to be exported and imported using XML with PHP DOMDocument. |
| Database storage | Stores users, guests, rooms, reservations, and payments in MySQL. |

## What The System Will Not Do

The system is not intended to support these features in the current version:

| Exclusion | Explanation |
| --- | --- |
| No mobile app version | The project is a web application only. It does not include Android, iOS, Flutter, or React Native. |
| No standalone offline mode | The browser assets are local, but the system still needs PHP, MySQL, and XAMPP running. It cannot work as a disconnected standalone app. |
| No real-time collaboration | The system does not include live multi-user editing, WebSockets, or shared real-time task lists. |
| No online payment gateway | Payments are recorded manually by admins. There is no Stripe, PayPal, GCash, Maya, or bank API integration yet. |
| No email/SMS notifications | The system does not currently send booking confirmations, payment reminders, or deadline notifications. |
| No automated room assignment | Rooms are selected manually. Automatic best-room matching is a future feature. |
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
| Fonts | Local Google Fonts files | Provides DM Sans and DM Serif Display typography without runtime CDN dependency. |
| Styling | Custom CSS | Provides project-specific public, admin, and auth page design. |
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
| Admin pages | `public/admin/*.php` | Provides dashboard, rooms, reservations, payments, and user management. |
| Shared layout | `public/includes/layout.php` | Renders common page header, navigation, admin shell, and scripts. |
| Database scripts | `database/*.sql` | Creates and seeds the database. |
| Assets | `public/assets/**` | Stores CSS, images, local libraries, logo, and fonts. |
| Docs | `docs/*.md` | Stores project documentation. |

## Deployment Environment

The intended deployment environment for this project is local XAMPP.

Recommended local setup:

1. Place the project folder inside `xampp/htdocs/`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Import `database/schema.sql` into MySQL.
4. Open the project in the browser through `http://localhost/emperor_hotel/`.

The project can also be moved to a PHP/MySQL web host, but the database credentials in `app/config/database.php` must be updated for that server.
