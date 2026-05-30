# Website Flowchart

This document shows the main website flow for guests, registered users, and administrators.

## Overall Website Flow

```mermaid
flowchart TD
    A[Visitor opens website] --> B[Public Home Page]
    B --> C[Suites and Rooms Page]
    C --> D{User wants to book?}
    D -->|No| B
    D -->|Yes| E{Already logged in?}
    E -->|No| F[Register or Login]
    E -->|Yes| G[User Dashboard]
    F --> G
    G --> H[Enter stay details and choose room card]
    H --> I[Select booking dates and review 5-person capacity]
    I --> I1[Review cost tracker and submit reservation]
    I1 --> J[Reservation saved as Pending]
    J --> J1{Payment Mode}
    J1 -->|Cash| J2[Generate Pending Cash Payment Reference]
    J1 -->|Card / Bank / Online| J3[Open Customer Payment Page]
    J3 --> J4[Submit Simulated Payment for Admin Review]
    J2 --> K[User views reservation list]
    J4 --> K

    F --> L{Admin account?}
    L -->|Yes| M[Admin Dashboard]
    L -->|No| G

    M --> N[Manage Rooms]
    M --> O[Create Reservation]
    M --> O12[Manage Booking Records]
    M --> P[Manage Payments]
    M --> Q[Manage Guests]
    M --> U[Manage Users]
    M --> R[View Dashboard Charts]
    M --> T[Open Reports]

    N --> N1[Create Room]
    N --> N2[Edit Room]
    N --> N3[Delete Room]
    N --> N4[Update Room Type Price]
    N --> N5[Import or Export Room XML]

    O --> O1[Check Dates and Create Reservation]
    O12 --> O2[Click Manage on Booking Record]
    O2 --> O3[View Reservation Details and Payment Totals]
    O3 --> O4[Confirm / Check In / Extend Stay / Check Out / Cancel]
    O3 --> O5[Open Printable Receipt]
    O3 --> O11[Delete Reservation]
    O --> O6{Payment Mode}
    O6 --> O7[Cash: Generate Pending Cash Payment Reference]
    O6 --> O8[Card / Bank / Online: Open Payments Page]
    O --> O9[Select Room Card Manually]

    P --> P1[Record Payment]
    P --> P2[View Transaction Report Log]
    P --> P3[View Payment Status Summary]
    P --> P4[Create Simulated Transaction]
    P --> P5[Review Pending Transactions]
    P --> P6[Prevent Overpayment]

    Q --> Q1[Search Guest]
    Q --> Q2[View Guest Reservation History]
    Q --> Q3[Book Again or Print Receipt]

    U --> U1[Create User]
    U --> U2[Edit User]
    U --> U3[Delete User]

    T --> T1[Filter Date Range]
    T --> T2[View Occupancy Report]
    T --> T3[View Revenue Report]
    T --> T4[View Reservation Trend Report]
```

## Guest Flow

```mermaid
flowchart TD
    A[Guest visits public site] --> B[View homepage]
    B --> C[View rooms and suites]
    C --> D[Check room types, images, prices, and up to 5-person capacity]
    D --> E[Login or register to book]
```

## Registered User Flow

```mermaid
flowchart TD
    A[User logs in] --> B[User Dashboard]
    B --> C[Enter full name, dates, phone, and payment route]
    C --> C1[Choose a room from the room selection panel]
    C1 --> D[Review room inclusions]
    D --> D1[Review reservation cost tracker]
    D1 --> E[Submit booking form]
    E --> F{Room available?}
    F -->|Yes| G[Reservation is saved]
    F -->|No| H[Show availability error]
    G --> I[Reservation appears in booking history below the form]
```

## Admin Flow

```mermaid
flowchart TD
    A[Admin logs in] --> B[Admin Dashboard]
    B --> C[View KPI cards and charts]
    B --> C1[Review operational alerts]
    B --> D[Rooms Module]
    B --> E[Reservations Module]
    B --> E10[Booking Records Module]
    B --> F[Payments Module]
    B --> G[Guests Module]
    B --> H[Users Module]
    B --> I[Reports Module]

    D --> D1[Add or edit rooms]
    D --> D2[Delete rooms]
    D --> D3[Bulk update room prices]
    D --> D4[Import or export room XML]

    E --> E1[Check date-aware room availability]
    E --> E2[Add reservation]
    E10 --> E3[Click Manage on booking record]
    E3 --> E4[View reservation details and payment totals]
    E4 --> E5[Confirm, check in, extend stay, check out, or cancel]
    E4 --> E6[Open printable receipt]
    E4 --> E9[Delete reservation]
    E --> E7[Select room card manually]

    F --> F1[Record payment or simulated transaction]
    F --> F2[Review payment history]
    F --> F3[Update transaction status]
    F --> F4[Prevent pending/confirmed overpayment]

    G --> G1[Search guests]
    G --> G2[View guest history]
    G --> G3[Book again or open receipt]

    H --> H1[Add user]
    H --> H2[Edit user]
    H --> H3[Delete user]

    I --> I1[Filter report date range]
    I --> I2[Review occupancy by room type]
    I --> I3[Review revenue by room type or payment method]
    I --> I4[Review daily reservation trends]
```

## Page Route Summary

| Page | Route | Main User |
| --- | --- | --- |
| Home | `public/site/home.php` | Guest, user, admin |
| Rooms | `public/site/rooms.php` | Guest, user, admin |
| Login | `public/auth/login.php` | Guest |
| Register | `public/auth/register.php` | Guest |
| Logout | `public/auth/logout.php` | Logged-in users |
| User Dashboard | `public/user/dashboard.php` | Registered user |
| User Payment | `public/user/payment.php` | Registered user |
| Admin Dashboard | `public/admin/dashboard.php` | Admin |
| Admin Rooms | `public/admin/rooms.php` | Admin |
| Admin Reservations | `public/admin/reservations.php` | Admin |
| Admin Booking Records | `public/admin/booking-records.php` | Admin |
| Admin Payments | `public/admin/payments.php` | Admin |
| Admin Guests | `public/admin/guests.php` | Admin |
| Admin Receipt | `public/admin/receipt.php` | Admin |
| Admin Reports | `public/admin/reports.php` | Admin |
| Admin Users | `public/admin/users.php` | Admin |
