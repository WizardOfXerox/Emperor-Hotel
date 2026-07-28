# Emperor Hotel — System Integrations Architecture Guide

This document details all **8 core feature, protocol, third-party API, and graphic integrations** powering the **Emperor Hotel Management & Reservation System**.

---

## 🔌 Core Integrations Matrix

| # | Integration | Type | Primary Files Involved | Functional Description |
|---|---|---|---|---|
| **1** | **Google Gemini API (`gemini-2.5-flash`)** | Third-Party REST API | `public/support/api.php`, `app/models/SupportAssistant.php` | Connects live MySQL analytical metrics (revenue, occupancy, ALOS, ADR, RevPAR) to Google Gemini AI to deliver database-aware chat support and inline HTML visual charts. |
| **2** | **Socket-Level SMTP Protocol** | Network Protocol | `app/helpers/mailer.php`, `public/auth/register.php` | Custom socket client (supporting SSL/TLS & `AUTH LOGIN`) delivering OTP codes, booking confirmations, and concierge email replies via Gmail, Outlook, etc. Includes offline connectivity fallback. |
| **3** | **IMAP Protocol Inbound Sync** | Network Protocol | `app/helpers/imap_fetcher.php`, `public/admin/messages.php` | Connects to external mailboxes (`imap.gmail.com` / `outlook.office365.com`) to automatically fetch guest email replies and sync them into the Admin Concierge Inbox. |
| **4** | **DNS MX Record Protocol (`checkdnsrr`)** | Network Protocol | `public/auth/register.php`, `app/helpers/mailer.php` | Performs live domain Mail Exchanger lookups during registration to verify that `@gmail.com`, `@outlook.com`, etc. accept emails before creating accounts. |
| **5** | **Chart.js 4.5.1 Visual Engine** | Client-Side JS Library | `public/assets/vendor/chartjs/`, `public/admin/reports.php` | Local charting library integrated with a custom `emperorValuePlugin` plugin to render interactive line, bar, doughnut, and combo charts with high-contrast data badges. |
| **6** | **Interactive SVG Vector Floorplan Engine** | Dynamic Vector Graphics | `public/includes/hotel_map.php`, `public/site/map.php` | Interactive 2D vector map visualizing all 36 hotel rooms across Floor 1, 2, and 3, sync'd in real time with live database statuses (`Available`, `Reserved`, `Occupied`, `Cleaning`, `Maintenance`). |
| **7** | **Bootstrap 5.3.3 & Bootstrap Icons** | UI Framework | `public/assets/vendor/bootstrap/`, `public/includes/` | Responsive UI components, modal dialogs, navigation bars, and luxury dark-mode visual elements. |
| **8** | **PHP Data Objects (PDO / MySQL)** | Database Abstraction | `app/config/database.php`, `app/models/*.php` | Secure transactional database connection layer utilizing prepared statements and row-level locking (`FOR UPDATE`) for concurrency safety. |

---

## 🌟 Deep-Dive into Unique System Integrations

### 1. Google Gemini AI Support & Live Database Analytics
* **Architecture**: When a user types a message in `support-widget.js`, it posts to `public/support/api.php`.
* **Database Context Injection**: The backend queries active rooms, monthly sales, occupancy rates, and average lead time, feeding this live context to `gemini-2.5-flash`.
* **Guest Suite Recommendations**: Automatically parses group sizes (e.g., *"for 5 people"*) and recommends matching room categories (e.g., *Emperor Presidential Suite — Max 6 Guests*) with direct booking buttons.

### 2. Socket SMTP Mailer with Offline Presentation Fallback
* **Architecture**: `app/helpers/mailer.php` executes socket-level SMTP commands (`EHLO`, `STARTTLS`, `AUTH LOGIN`, `MAIL FROM`, `RCPT TO`, `DATA`).
* **Offline Probe**: Before attempting network sockets, `isInternetConnected()` probes `8.8.8.8:53`. If offline or missing SMTP credentials, it seamlessly triggers **Presentation Mode**, displaying verification OTPs directly in flash banners so local demos and tests never hang.

### 3. Bidirectional Concierge Email Sync (SMTP + IMAP)
* **Outbound**: Staff type replies in `public/admin/messages.php`, which dispatches via SMTP to the guest's email inbox.
* **Inbound**: `imap_fetcher.php` pulls guest responses from the hotel IMAP mailbox and threads them continuously inside `contact_messages`.

### 4. Interactive 2D Map & Live Database State Machine
* **Visual Sync**: `public/includes/hotel_map.php` renders an SVG layout of Floor 1 (*Imperial Deluxe*), Floor 2 (*Royal Executive*), and Floor 3 (*Emperor Presidential*).
* **Real-Time Colors**: Rooms dynamically change color based on database states:
  * 🟢 **Available** (Green)
  * 🟡 **Reserved** (Yellow)
  * 🔴 **Occupied** (Red)
  * 🔵 **Cleaning** (Blue)
  * ⚪ **Maintenance** (Gray)

---

## 📂 Related Architecture Documentation
* **[system_code_flow_and_process_guide.md](file:///c:/Users/XIA/Documents/xampp/htdocs/emperor_hotel/docs/system_code_flow_and_process_guide.md)** — File-to-file code flows and sequence diagrams.
* **[technology-stack.md](file:///c:/Users/XIA/Documents/xampp/htdocs/emperor_hotel/docs/technology-stack.md)** — Third-party library inventory.
