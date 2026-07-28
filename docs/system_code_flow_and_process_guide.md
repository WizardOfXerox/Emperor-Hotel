# Emperor Hotel — Full System Deep Scan: Code Flow & Feature Process Guide

This document presents a complete, end-to-end architectural map of the **Emperor Hotel Management & Reservation System**. It details how HTTP requests move from **file to file**, how data flows between backend models and the MySQL database, and how all core features execute.

---

## 🏛️ 1. Core Architecture & Bootstrap Pipeline

Every user or admin request passes through a unified bootstrap entry point before reaching page controllers or API endpoints.

```mermaid
flowchart TD
    A[Browser / Client Request] --> B["public/includes/bootstrap.php"]
    B --> C["app/config/database.php (DB Config & PDO Connection)"]
    B --> D["app/config/hotel.php (Hotel Identity & Rates)"]
    B --> E["app/helpers/auth.php (Session Management & RBAC)"]
    B --> F["app/helpers/mailer.php (SMTP Mailer & Connectivity Probe)"]
    E --> G{Authenticated?}
    G -- Yes --> H[Load Requested Controller / Page]
    G -- No --> I[Enforce Access Rules / Guest Access]
```

### File-to-File Data Dependency Pipeline
1. `public/includes/bootstrap.php`: Starts native PHP sessions, defines root directory constants, loads helper files.
2. `app/config/database.php`: Manages the singleton `Database::connect()` PDO database instance.
3. `app/helpers/auth.php`: Defines `currentUser()`, `requireLogin()`, `requireAdmin()`, `generateCsrfToken()`, and `validateCsrfToken()`.
4. `app/models/*.php`: Instantiated dynamically with the active PDO instance (`new Reservation($db)`, `new Room($db)`, etc.).

---

## 🔑 2. User Authentication & 2FA Process Flow

Handles guest registration, live DNS MX record checks, 6-digit OTP verification, session authorization, and password resets.

```mermaid
sequenceDiagram
    autonumber
    actor Guest
    participant RegPage as public/auth/register.php
    participant Mailer as app/helpers/mailer.php
    participant OTPPage as public/auth/verify-otp.php
    participant UserModel as app/models/User.php
    participant DB as MySQL Database

    Guest->>RegPage: Submit Registration Form (Name, Email, Password)
    RegPage->>RegPage: Validate Password Length & Email Format
    RegPage->>RegPage: Perform checkdnsrr($domain, 'MX') Check
    RegPage->>UserModel: Check if Email Exists (findByEmail)
    UserModel->>DB: SELECT * FROM users WHERE email = ?
    DB-->>UserModel: Email Available
    RegPage->>UserModel: Create User Record (Status: Pending_OTP)
    UserModel->>DB: INSERT INTO users (name, email, password_hash, otp_code, otp_expires_at)
    RegPage->>Mailer: sendSmtpEmail($email, $otpCode)
    Mailer->>Mailer: Probe Socket 8.8.8.8:53 (Internet Check)
    alt Internet Connected & SMTP Configured
        Mailer->>Mailer: Send 6-Digit OTP via Socket SMTP
    else Offline or Missing Credentials
        Mailer->>RegPage: Flash Banner OTP Presentation Fallback
    end
    RegPage-->>Guest: Redirect to verify-otp.php
    Guest->>OTPPage: Enter 6-Digit OTP Code
    OTPPage->>UserModel: Validate OTP Code & Expiration
    UserModel->>DB: UPDATE users SET is_verified = 1, status = 'Active', otp_code = NULL
    OTPPage-->>Guest: Flash Success & Redirect to login.php
```

### Key Files Involved:
* `public/auth/register.php` $\rightarrow$ Registration form & MX domain lookup.
* `public/auth/verify-otp.php` $\rightarrow$ 6-digit OTP verification & account activation.
* `public/auth/login.php` $\rightarrow$ Password verification (`password_verify`) & session creation.
* `public/auth/forgot-password.php` $\rightarrow$ Password reset token generation & email dispatch.
* `app/models/User.php` $\rightarrow$ User CRUD & authentication query methods.

---

## 🏨 3. Room Discovery, 2D Map Selection & Booking Flow

Covers how guests browse room categories, inspect real-time availability on the interactive 2D map, submit reservation details, and lock inventory.

```mermaid
flowchart TD
    A["Guest Browses Suites (public/site/rooms.php)"] --> B["View Interactive 2D Map (public/site/map.php)"]
    B --> C["Fetch Live Room States (map_availability.php)"]
    C --> D["Select Specific Room # (e.g. Room 201)"]
    D --> E["Fill Stay Dates & Guest Details (public/user/dashboard.php)"]
    E --> F["Submit Booking Request"]
    F --> G["Reservation::create() in app/models/Reservation.php"]
    G --> H["Check Overlapping Bookings (hasConflict)"]
    H -- Conflict Exists --> I[Show Error: Room Unavailable for Selected Dates]
    H -- Room Available --> J["INSERT INTO reservations (user_id, room_id, check_in, check_out, total_amount)"]
    J --> K["Room::updateStatus(room_id, 'Reserved')"]
    K --> L["Payment::createPendingPayment() in app/models/Payment.php"]
    L --> M{Payment Option Selected?}
    M -- Cash at Desk --> N[Create Pending Cash Balance Reference & Show Instructions]
    M -- Card / E-Wallet --> O["Redirect to Payment Screen (public/user/payment.php)"]
```

### Key Files Involved:
* `public/site/rooms.php` & `public/site/room-detail.php` $\rightarrow$ Suite catalog & details.
* `public/site/map.php` & `public/site/map_availability.php` $\rightarrow$ Interactive 2D vector room map.
* `public/user/dashboard.php` $\rightarrow$ Guest dashboard & reservation creation controller.
* `app/models/Reservation.php` $\rightarrow$ Overlap collision detection (`hasConflict()`) & SQL transaction logging.
* `app/models/Room.php` $\rightarrow$ Room status lifecycle manager.

---

## 💰 4. Multi-Channel Payment & Financial Accounting Flow

Manages payment verification, partial/full balance tracking, receipt printing, and automated reservation status promotion.

```mermaid
sequenceDiagram
    autonumber
    actor Guest/Admin
    participant PayPage as public/user/payment.php / admin/payments.php
    participant PayModel as app/models/Payment.php
    participant ResModel as app/models/Reservation.php
    participant RoomModel as app/models/Room.php
    participant ReceiptPage as public/user/receipt.php
    participant DB as MySQL Database

    Guest/Admin->>PayPage: Submit Payment (Cash / Card / E-Wallet / Bank Transfer)
    PayPage->>PayModel: recordPayment(reservation_id, amount, payment_method, reference_no)
    PayModel->>DB: INSERT INTO payments (reservation_id, amount, payment_method, status, transaction_ref)
    PayModel->>ResModel: syncRoomStatus(reservation_id)
    ResModel->>DB: Calculate SUM(amount) WHERE status = 'Confirmed'
    alt Total Paid >= (Total Amount - 0.01)
        ResModel->>DB: UPDATE reservations SET status = 'Confirmed' WHERE reservation_id = ?
        ResModel->>RoomModel: UPDATE rooms SET status = 'Reserved' WHERE room_id = ?
    else Partial Payment
        ResModel->>DB: Keep reservation status = 'Pending' / Record Partial Balance
    end
    PayPage-->>Guest/Admin: Payment Confirmation Flash
    Guest/Admin->>ReceiptPage: Click "📄 Receipt" Button
    ReceiptPage->>ResModel: Fetch Reservation & Itemized Payment Rows
    ReceiptPage-->>Guest/Admin: Render Official Printable Itemized Payment Receipt
```

### Key Files Involved:
* `public/user/payment.php` $\rightarrow$ Guest online payment gateway simulation.
* `public/admin/payments.php` $\rightarrow$ Admin payment approval, refund logging, & manual record entry.
* `public/user/receipt.php` & `public/admin/receipt.php` $\rightarrow$ Printable itemized payment receipts.
* `app/models/Payment.php` $\rightarrow$ Financial accounting rules & transaction loggers.

---

## 🤖 5. AI Support Assistant & Analytics Engine Flow

Integrates the Google Gemini API with local database queries to deliver intelligent room recommendations, concierge answers, and executive visual charts.

```mermaid
flowchart TD
    A["User Types Message in Support Widget (support-widget.js)"] --> B["POST to public/support/api.php"]
    B --> C["Instantiate SupportAssistant($db) in app/models/SupportAssistant.php"]
    C --> D{"Detect Scope & Intent (respond)"}
    
    D -- Specific Dataset Match --> E["findAllDatasetMatches($prompt)"]
    E --> F["datasetMultiReply() (Generate Composite HTML Concierge Card)"]
    
    D -- Guest Capacity Query (e.g. 'for 5 people') --> G["customerRecommendationReply(5)"]
    G --> H["Render Gold Recommendation Card (Emperor Presidential Suite - Max 6 Guests)"]
    
    D -- Admin Analytical Query --> I["Compose Live Database Metrics (Revenue, ALOS, ADR, Occupancy)"]
    I --> J{"GEMINI_API_KEY Configured & Online?"}
    J -- Yes --> K["askGeminiSupport() -> Call gemini-2.5-flash REST Endpoint"]
    J -- No / Fallback --> L["Local Analytics Graph Builder (buildVisualSalesBarChart)"]
    
    F --> M[Return JSON Response to Frontend]
    H --> M
    K --> M
    L --> M
    M --> N["support-widget.js Renders Dynamic HTML / SVG Charts in Chat Drawer"]
```

### Key Files Involved:
* `public/assets/js/support-widget.js` $\rightarrow$ Frontend chat drawer widget UI handler.
* `public/support/api.php` $\rightarrow$ API endpoint & Gemini REST client (`askGeminiSupport`).
* `app/models/SupportAssistant.php` $\rightarrow$ Natural language parser, dataset matcher, & visual graph generator.

---

## 📩 6. Guest Concierge Inbox & Email Thread Sync Flow

Enables bi-directional communication between guests and hotel staff across web forms and external mail clients.

```mermaid
sequenceDiagram
    autonumber
    actor Guest
    participant ContactForm as public/site/contact.php
    participant ContactModel as app/models/ContactMessage.php
    participant AdminInbox as public/admin/messages.php
    participant Mailer as app/helpers/mailer.php
    participant IMAP as app/helpers/imap_fetcher.php
    actor Staff

    Guest->>ContactForm: Submit Inquiry Message
    ContactForm->>ContactModel: createInquiry(name, email, subject, message)
    ContactModel->>ContactModel: Save to contact_messages Table
    Staff->>AdminInbox: Open Concierge Desk Inbox
    AdminInbox->>ContactModel: Fetch Inquiry Threads
    Staff->>AdminInbox: Type Reply & Click "Send Reply"
    AdminInbox->>Mailer: sendSmtpEmail(guest_email, subject, reply_body)
    Mailer->>Guest: Deliver Email to Guest Inbox (Gmail/Outlook)
    Guest->>Mailer: Reply via Email Client (Outlook/Gmail)
    IMAP->>IMAP: Cron / Manual Trigger imap_fetcher.php
    IMAP->>ContactModel: Sync Guest Email Reply into DB Thread
    ContactModel-->>AdminInbox: Display Continuous Conversation Thread
```

### Key Files Involved:
* `public/site/contact.php` $\rightarrow$ Guest contact & inquiry web form.
* `public/admin/messages.php` $\rightarrow$ Admin Concierge Desk inbox & email reply dashboard.
* `app/models/ContactMessage.php` $\rightarrow$ Message thread database manager.
* `app/helpers/mailer.php` $\rightarrow$ Outbound SMTP delivery.
* `app/helpers/imap_fetcher.php` $\rightarrow$ Inbound IMAP email fetcher.

---

## 📊 7. Admin Executive Reporting & Chart.js Visual Flow

Processes historical reservations and payment data into interactive Chart.js canvas visualizations and tabular data toggles.

```mermaid
flowchart TD
    A["Admin Accesses Reports (public/admin/reports.php)"] --> B["Query Historical Metrics (Reservation.php & Payment.php)"]
    B --> C["Compute Financial & Operational Indicators"]
    C --> D["Daily Booking Demand Trends"]
    C --> E["Payment Method Revenue Share %"]
    C --> F["Booked Room Nights per Suite Category"]
    C --> G["Average Guest Rating Scores"]
    D & E & F & G --> H["Inject JSON Data into Browser Page"]
    H --> I["Chart.js 4.5.1 Initialization (chart.umd.min.js)"]
    I --> J["Apply Custom Plugin (emperorValuePlugin) for Data Badges"]
    J --> K["Render Line, Bar, and Doughnut Charts on HTML Canvases"]
    K --> L["User Clicks [Graph] / [Table] Toggle"]
    L --> M["Instantly Switch View Between Interactive Canvas Chart and Tabular Data Card"]
```

### Key Files Involved:
* `public/admin/reports.php` $\rightarrow$ Executive analytics & visual reports dashboard.
* `public/admin/dashboard.php` $\rightarrow$ Main admin overview dashboard with KPI cards & alerts.
* `public/assets/vendor/chartjs/chart.umd.min.js` $\rightarrow$ Local Chart.js 4.5.1 library.
* `app/models/Reservation.php` & `app/models/Payment.php` $\rightarrow$ Data aggregation engines.

---

## 📂 File Directory Structure Summary

```
c:\Users\XIA\Documents\xampp\htdocs\emperor_hotel\
├── app/
│   ├── config/
│   │   ├── database.php          # Database PDO connection setup
│   │   └── hotel.php             # Hotel configuration & profile metadata
│   ├── helpers/
│   │   ├── auth.php              # Session auth, RBAC, CSRF helpers
│   │   ├── imap_fetcher.php      # Inbound email IMAP sync helper
│   │   └── mailer.php            # Outbound socket SMTP mailer & offline probe
│   └── models/
│       ├── ContactMessage.php    # Guest contact messages & replies
│       ├── Guest.php             # Guest profiles & history
│       ├── Payment.php           # Payment transactions & balance logic
│       ├── Reservation.php       # Reservation engine & date collision checks
│       ├── Review.php            # Verified guest reviews & rating scores
│       ├── Room.php               # Room inventory & status state machine
│       ├── SupportAssistant.php  # AI & dataset support assistant
│       └── User.php              # User accounts & 2FA authentication
├── public/
│   ├── admin/                    # Admin portal pages (dashboard, reservations, rooms, etc.)
│   ├── assets/                   # CSS, local fonts, JS, Chart.js vendor files
│   ├── auth/                     # Auth pages (login, register, verify-otp, forgot-password)
│   ├── includes/                 # Common components (bootstrap, navbar, footer, map)
│   ├── site/                     # Public marketing pages (rooms, suites, contact, about)
│   ├── support/                  # Support assistant REST API endpoint (api.php)
│   └── user/                     # Guest dashboard, payment, and receipt pages
└── database/
    └── seed_large_dataset.sql    # Clean database seed dump file
```
