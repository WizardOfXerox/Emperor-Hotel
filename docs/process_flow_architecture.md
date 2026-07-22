# 🏰 EMPEROR HOTEL RESERVATION SYSTEM - END-TO-END PROCESS FLOW ARCHITECTURE

This document outlines the complete process flow and data lifecycle of the **Emperor Hotel Reservation System**, detailing how guest interactions on the public site transition into reservations, cost calculations, payment routing, and administrative management across Rooms, Reservations, Booking Records, and Payments.

---

## 📐 1. High-Level System Architecture & Page Flow

```mermaid
flowchart TD
    subgraph PUBLIC["PUBLIC GUEST FRONTEND"]
        HOME["home.php<br>(Side-by-Side Calendar & Floor Map)"]
        SUITES["rooms.php<br>(Suites Showcase Catalog)"]
        ROOM_DETAIL["room-detail.php<br>(5-Star Obsidian Showcase & Price Calculator)"]
    end

    subgraph AUTH["AUTHENTICATION LAYER"]
        LOGIN["login.php<br>(Customer / Admin Login)"]
        REGISTER["register.php<br>(Account Registration)"]
    end

    subgraph USER_DASHBOARD["CUSTOMER DASHBOARD"]
        DASHBOARD["user/dashboard.php<br>(Prefilled Reservation Form & Cost Tracker)"]
        PAYMENT_GATEWAY["user/payment.php<br>(Online Payment Gateway Simulation)"]
    end

    subgraph ADMIN_PANEL["ADMIN MANAGEMENT BACKEND"]
        ADMIN_DASH["admin/dashboard.php<br>(Analytics, KPIs & Watchlist)"]
        ADMIN_ROOMS["admin/rooms.php<br>(Interactive Floor Map & Inventory CRUD)"]
        ADMIN_RES["admin/reservations.php<br>(Check-in / Check-out Status Workflow)"]
        ADMIN_RECORDS["admin/records.php<br>(Historical Booking Logs & Audit Trail)"]
        ADMIN_PAYMENTS["admin/payments.php<br>(Payment Verification & Settlement)"]
    end

    HOME -->|Select Dates & Room| ROOM_DETAIL
    SUITES -->|Inspect Room| ROOM_DETAIL
    ROOM_DETAIL -->|Reserve Room Now| DASHBOARD
    
    DASHBOARD -->|Unauthenticated| LOGIN
    LOGIN -->|After Auth| DASHBOARD
    REGISTER -->|After Registration| LOGIN

    DASHBOARD -->|Pay Cash| ADMIN_PAYMENTS
    DASHBOARD -->|Pay Online/Card| PAYMENT_GATEWAY
    PAYMENT_GATEWAY -->|Confirmed| DASHBOARD

    LOGIN -->|Role: Admin| ADMIN_DASH
    ADMIN_DASH --> ADMIN_ROOMS
    ADMIN_DASH --> ADMIN_RES
    ADMIN_DASH --> ADMIN_RECORDS
    ADMIN_DASH --> ADMIN_PAYMENTS
```

---

## 🔄 2. Customer Booking & Payment Process Lifecycle

```mermaid
sequenceDiagram
    autonumber
    actor Guest as Customer / Guest
    participant Home as Home Page / Floor Map
    participant Detail as Room Detail Page
    participant Dash as User Dashboard
    participant DB as MySQL Database
    participant Admin as Admin Panel

    Guest->>Home: Select Stay Dates (Check-In & Check-Out) on 7-Column Calendar
    Home->>Home: Query `map_availability.php` AJAX API & Filter Floor Map in-place
    Guest->>Home: Click Available Room Block (#101)
    Home->>Detail: Navigate to `room-detail.php?id=1&check_in=...&check_out=...`
    Detail->>Detail: Calculate Stay Nights & Estimated Rate (₱8,500/night x Nights)
    Guest->>Detail: Click "Reserve Room #101 Now"
    Detail->>Dash: Redirect to `user/dashboard.php?selected_room=1&check_in=...`
    Dash->>Dash: Prefill Date Inputs, Check Room #101 Card, Run Cost Tracker
    Guest->>Dash: Select Payment Method (Cash or Online) & Submit Reservation
    Dash->>DB: INSERT into `guests`, `reservations` (Status: Pending), `payments`
    
    alt Choice 1: Cash Payment
        Dash-->>Guest: Generate Payment Transaction Reference (Show at Cashier)
    else Choice 2: Online Payment / Card
        Dash->>Guest: Redirect to `user/payment.php` Simulation
        Guest->>Dash: Complete Payment Simulation
        Dash->>DB: UPDATE `payments` (Status: Confirmed)
    end

    Admin->>DB: Admin reviews reservation on `admin/reservations.php`
    Admin->>DB: Update Reservation Status (Pending -> Confirmed -> Checked-In -> Checked-Out)
    DB->>Home: Room Status automatically syncs on Floor Map & Availability endpoints
```

---

## 🛡️ 3. Administrative Operational Data Flow

Below is the detailed breakdown of how data flows across the **4 Core Admin Dashboards**:

```mermaid
flowchart LR
    subgraph ADMIN_MODULES["ADMINISTRATIVE DASHBOARD MODULES"]
        direction TB
        
        R["1. ROOMS MODULE<br>(admin/rooms.php)"]
        RES["2. RESERVATIONS MODULE<br>(admin/reservations.php)"]
        REC["3. BOOKING RECORDS<br>(admin/records.php)"]
        P["4. PAYMENTS MODULE<br>(admin/payments.php)"]
    end

    R -->|Room Status & Inventory| RES
    RES -->|Active Stays & Check-ins| REC
    RES -->|Total Due & Invoices| P
    P -->|Payment Status Settlement| RES
```

### 1. 🚪 Rooms Management Module (`admin/rooms.php`)
- **Primary Function**: Manages the physical hotel inventory and 2D spatial arrangement across floors.
- **Data Flow**:
  1. Displays the **Interactive 2D Hotel Floor Map** for real-time visual inspection (Floor 1: #101–#112, Floor 2: #201–#212, Floor 3: #301–#312).
  2. Clicking any room card opens the **Admin Room Status Modal** allowing status updates (`Available`, `Reserved`, `Occupied`, `Cleaning`, `Maintenance`).
  3. Allows adding new room records, updating room types or rates, filtering inventory, and exporting XML data (`rooms-export.xml`).
  4. Changes immediately update room availability across the public Floor Map and customer booking engine.

### 2. 📅 Reservations Module (`admin/reservations.php`)
- **Primary Function**: Handles guest booking lifecycles, arrival check-ins, and departure check-outs.
- **Data Flow**:
  1. Receives incoming guest reservations created from `user/dashboard.php`.
  2. Administrators review booking details (Check-in/out dates, guest contact info, assigned room, total amount).
  3. **Status Workflow Transition**:
     $$\text{Pending} \longrightarrow \text{Confirmed} \longrightarrow \text{Checked-in} \longrightarrow \text{Checked-out}$$
  4. Marking a reservation as **Checked-in** automatically marks the room as `Occupied` on the Floor Map.
  5. Marking a reservation as **Checked-out** marks the room as `Cleaning` / `Available` for future dates.

### 3. 📜 Booking Records Module (`admin/records.php`)
- **Primary Function**: Audit trail and historical ledger of all past, present, and cancelled bookings.
- **Data Flow**:
  1. Archival repository for completed (`Checked-out`) and `Cancelled` reservations.
  2. Enables filtering by guest name, date ranges, room types, or status.
  3. Provides analytics input for monthly performance revenue metrics and peak booking reports.

### 4. 💳 Payments Module (`admin/payments.php`)
- **Primary Function**: Financial accounting, cashier cash confirmation, and transaction settlement.
- **Data Flow**:
  1. Generates unique transaction references (`EMP-PAY-XXXXX`) for every reservation.
  2. For **Cash Payments**: Front desk cashiers look up payment references when guests pay at the front desk and update payment status to `Confirmed`.
  3. For **Online Payments**: Simulated card/online payments automatically update payment status to `Confirmed`.
  4. Updates the guest's balance due on their User Dashboard from `Balance: PHP X,XXX` to `Paid`.

---

## 🔄 4. Reservation & Payment State Machine

```mermaid
stateDiagram-v2
    [*] --> Pending: Customer submits reservation on user/dashboard.php
    
    state Pending {
        [*] --> UnpaidCash: Method = Cash
        [*] --> OnlineSim: Method = Card / Online
    }

    UnpaidCash --> Confirmed: Front desk confirms cash payment on admin/payments.php
    OnlineSim --> Confirmed: Online payment completed on user/payment.php
    
    Pending --> Cancelled: Customer or Admin cancels booking
    
    Confirmed --> CheckedIn: Guest arrives & Admin clicks "Check-in"
    CheckedIn --> CheckedOut: Guest departs & Admin clicks "Check-out"
    
    CheckedOut --> [*]: Reservation completed & archived in Booking Records
    Cancelled --> [*]: Archived in Booking Records
```
