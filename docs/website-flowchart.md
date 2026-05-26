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
    G --> H[Choose room and booking dates]
    H --> I[Submit reservation]
    I --> J[Reservation saved as Pending]
    J --> K[User views reservation list]

    F --> L{Admin account?}
    L -->|Yes| M[Admin Dashboard]
    L -->|No| G

    M --> N[Manage Rooms]
    M --> O[Manage Reservations]
    M --> P[Manage Payments]
    M --> Q[Manage Users]
    M --> R[View Dashboard Charts]

    N --> N1[Create Room]
    N --> N2[Edit Room]
    N --> N3[Delete Room]
    N --> N4[Update Room Type Price]
    N --> N5[Import or Export XML]

    O --> O1[Create Reservation]
    O --> O2[Edit Reservation]
    O --> O3[Delete Reservation]

    P --> P1[Record Payment]
    P --> P2[View Payment History]
    P --> P3[View Payment Status Summary]

    Q --> Q1[Create User]
    Q --> Q2[Edit User]
    Q --> Q3[Delete User]
```

## Guest Flow

```mermaid
flowchart TD
    A[Guest visits public site] --> B[View homepage]
    B --> C[View rooms and suites]
    C --> D[Check room types, images, prices, and capacity]
    D --> E[Login or register to book]
```

## Registered User Flow

```mermaid
flowchart TD
    A[User logs in] --> B[User Dashboard]
    B --> C[View available rooms]
    C --> D[Enter check-in and check-out dates]
    D --> E[Submit booking form]
    E --> F{Room available?}
    F -->|Yes| G[Reservation is saved]
    F -->|No| H[Show availability error]
    G --> I[Reservation appears in user's reservation list]
```

## Admin Flow

```mermaid
flowchart TD
    A[Admin logs in] --> B[Admin Dashboard]
    B --> C[View KPI cards and charts]
    B --> D[Rooms Module]
    B --> E[Reservations Module]
    B --> F[Payments Module]
    B --> G[Users Module]

    D --> D1[Add or edit rooms]
    D --> D2[Delete rooms]
    D --> D3[Bulk update room prices]
    D --> D4[Import or export room XML]

    E --> E1[Add reservation]
    E --> E2[Edit reservation]
    E --> E3[Delete reservation]

    F --> F1[Record payment]
    F --> F2[Review payment history]

    G --> G1[Add user]
    G --> G2[Edit user]
    G --> G3[Delete user]
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
| Admin Dashboard | `public/admin/dashboard.php` | Admin |
| Admin Rooms | `public/admin/rooms.php` | Admin |
| Admin Reservations | `public/admin/reservations.php` | Admin |
| Admin Payments | `public/admin/payments.php` | Admin |
| Admin Users | `public/admin/users.php` | Admin |
