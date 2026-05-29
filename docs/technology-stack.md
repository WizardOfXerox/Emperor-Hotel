# Technology Stack and Libraries

This document lists the main technologies, libraries, browser assets, and PHP features used in the Emperor Hotel Reservation and Management System.

## Summary

The project is a Core PHP/MySQL hotel reservation and management system. It does not require Node.js, npm, Vite, React, Laravel, Composer, or a frontend build step.

The frontend uses Bootstrap, Bootstrap Icons, Google Fonts, Chart.js, custom CSS, and local images. All browser libraries are stored inside the project so the app can run offline in XAMPP after the repository files are available.

## Server-Side Technologies

| Tool / Feature | Version / Type | Where Used | Purpose |
| --- | --- | --- | --- |
| Core PHP | XAMPP PHP runtime | `public/**/*.php`, `app/**/*.php` | Main backend language and page rendering |
| PHP OOP | Native PHP classes | `app/models/*.php`, `app/config/database.php` | Keeps database and business logic organized in classes |
| MySQL | XAMPP MySQL / MariaDB compatible | `database/schema.sql`, `database/seed_rooms.sql` | Stores users, guests, rooms, reservations, and payments |
| PDO | PHP extension | `app/config/database.php`, model classes | Database connection and prepared statements |
| PHP Sessions | Native PHP sessions | `app/helpers/auth.php`, auth pages | Login state, user sessions, and flash messages |
| `password_hash()` / `password_verify()` | Native PHP password API | `app/models/User.php` | Secure password storage and login verification |
| DOMDocument | PHP XML DOM extension | `app/models/Room.php` | Room XML export and import |

## Frontend Libraries

| Library | Version | Load Type | Local Path | Purpose |
| --- | --- | --- | --- | --- |
| Bootstrap CSS | `5.3.3` | Local file | `public/assets/vendor/bootstrap/css/bootstrap.min.css` | Responsive layout, grid, buttons, forms, tables |
| Bootstrap Bundle JS | `5.3.3` | Local file | `public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js` | Bootstrap carousel and interactive components |
| Bootstrap Icons | `1.11.3` | Local file | `public/assets/vendor/bootstrap-icons/bootstrap-icons.css` | Admin sidebar and UI icons |
| Chart.js | `4.5.1` | Local file | `public/assets/vendor/chartjs/chart.umd.min.js` | Dashboard charts |
| Google Fonts | DM Sans, DM Serif Display | Local font files | `public/assets/vendor/fonts/google-fonts.css` | Typography for public and admin pages |

## Local Asset Folders

| Asset | Location | Purpose |
| --- | --- | --- |
| Shared CSS | `public/assets/css/app.css` | Shared layout, navigation, panels, forms, tables, room picker, room inclusions, and cost tracker styles used by multiple pages |
| Admin page CSS | `public/assets/css/admin/*.css` | Admin page-specific styling for dashboard, rooms, reservations, payments, guests, reports, receipt, and users |
| Auth page CSS | `public/assets/css/auth/login.css`, `public/assets/css/auth/register.css` | Login/register page-specific styling |
| Site page CSS | `public/assets/css/site/home.css`, `public/assets/css/site/rooms.css` | Public home and rooms page-specific styling |
| User page CSS | `public/assets/css/user/dashboard.css`, `public/assets/css/user/payment.css` | Customer booking dashboard, side-by-side room selection layout, booking history, and customer payment page styling |
| Hotel logo SVG | `public/assets/images/branding/emperors-hotel-logo.svg` | Favicon and site branding |
| Home hero image | `public/assets/images/home/hero.jpg` | Public hero and admin background image |
| Room images | `public/assets/images/rooms/**` | Room hero images and carousel images |
| Vendor libraries | `public/assets/vendor/**` | Offline-ready third-party browser files |

## Runtime Includes

The shared layout loads the offline browser assets from `public/includes/layout.php`.

```html
<link href="../assets/vendor/fonts/google-fonts.css" rel="stylesheet">
<link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
```

```html
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
```

The dashboard loads Chart.js locally from `public/admin/dashboard.php`.

```html
<script src="../assets/vendor/chartjs/chart.umd.min.js"></script>
```

## Chart.js Usage

Chart.js is downloaded locally, so the dashboard charts do not need internet access.

Used in:

- `public/admin/dashboard.php`

Dashboard charts currently rendered:

- Monthly rooms booked and confirmed revenue combo chart
- Reservation status doughnut chart
- Room status doughnut chart
- Payment status doughnut chart

Data flow:

1. PHP model methods query MySQL.
2. `public/admin/dashboard.php` formats chart arrays.
3. PHP passes chart data to JavaScript using `json_encode()`.
4. Chart.js renders charts in browser `<canvas>` elements.

## CSS Organization

`public/assets/css/app.css` is reserved for styles used by more than one page. Page-only styling should live in a matching page CSS file and be loaded through the `$extraStylesheets` argument in `renderHeader()`, `renderSiteLayoutStart()`, or `renderAdminLayoutStart()`.

Current page-specific CSS groups:

| Page Group | CSS Files |
| --- | --- |
| Public site pages | `public/assets/css/site/home.css`, `public/assets/css/site/rooms.css` |
| Auth pages | `public/assets/css/auth/login.css`, `public/assets/css/auth/register.css` |
| User pages | `public/assets/css/user/dashboard.css`, `public/assets/css/user/payment.css` |
| Admin pages | `public/assets/css/admin/dashboard.css`, `public/assets/css/admin/rooms.css`, `public/assets/css/admin/reservations.css`, `public/assets/css/admin/payments.css`, `public/assets/css/admin/guests.css`, `public/assets/css/admin/reports.css`, `public/assets/css/admin/receipt.css`, `public/assets/css/admin/users.css` |

## Bootstrap Usage

Bootstrap is stored locally in `public/assets/vendor/bootstrap/`.

Used for:

- Responsive grids
- Buttons
- Forms
- Tables
- Alerts
- Carousels
- Utility classes

Bootstrap JavaScript is included through the bundled file, so Popper support is already included.

## Bootstrap Icons Usage

Bootstrap Icons is stored locally in `public/assets/vendor/bootstrap-icons/`.

Used mostly for:

- Admin sidebar icons
- Visual indicators in admin pages

The icon font files are stored in `public/assets/vendor/bootstrap-icons/fonts/`.

## Google Fonts Usage

Fonts:

- `DM Sans`
- `DM Serif Display`

The local stylesheet is `public/assets/vendor/fonts/google-fonts.css`.

The font files are stored in `public/assets/vendor/fonts/files/`.

## Offline Readiness

Offline-ready browser assets:

- Bootstrap CSS
- Bootstrap JS bundle
- Bootstrap Icons
- Google Fonts
- Chart.js
- Custom CSS
- Images
- Logo
- PHP/MySQL code

Current rule:

Do not add new runtime CDN links unless the project intentionally gives up offline readiness. If a new browser library is needed, download it into `public/assets/vendor/` and document it here.
