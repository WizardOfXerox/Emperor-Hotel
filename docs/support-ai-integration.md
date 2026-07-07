# AI Support Integration

This document explains the Emperor Hotel AI support system in detail: what files are involved, how the request flow works, what data is used, and how the assistant decides between a built-in database response and Gemini.

## 1. High-Level Purpose

The support widget gives the hotel two AI-style help modes:

- **Customer support** for guests asking about rooms, prices, room types, and hotel information.
- **Admin support** for staff asking about dashboard totals, sales, occupancy, room inventory, and report statistics.

The goal is to answer common questions from live hotel data first, and use Gemini only when the question is too open-ended for a direct database response.

## 2. Main Files Involved

| File | Responsibility |
| --- | --- |
| `public/includes/layout.php` | Renders the support widget container on customer and admin pages. |
| `public/assets/js/support-widget.js` | Handles the floating button, chat window, sidebar mode, quick prompts, message rendering, and requests to the support API. |
| `public/support/api.php` | Receives chat messages, calls the PHP support assistant, and sends requests to Gemini when needed. |
| `app/models/SupportAssistant.php` | Contains the local support intelligence, dataset routing, DB-backed answers, and admin statistics logic. |
| `app/models/Room.php` | Provides room availability, room inventory summaries, and room-type summaries. |
| `app/models/Reservation.php` | Provides dashboard counts, monthly performance, trend reports, occupancy reports, and operational alerts. |
| `app/models/Payment.php` | Provides revenue summaries and revenue reports by date range. |
| `app/config/hotel.php` | Stores hotel identity details such as name, description, and founding year. |

## 3. Request Flow

When the user sends a message in the widget, the flow is:

1. The browser collects the message, current scope (`admin` or `customer`), recent message history, and extracted keywords.
2. `public/support/api.php` receives the payload.
3. The API creates `SupportAssistant` with a live PDO database connection.
4. `SupportAssistant::respond()` decides whether the reply should come from:
   - a greeting response,
   - a built-in dataset response,
   - a direct database-backed room/customer/admin response,
   - a statistics-focused admin response,
   - or the AI fallback path.
5. If the assistant returns `kind = ai`, the API builds a scoped Gemini prompt and sends the prepared context to Gemini.
6. The final response is returned to the widget and shown to the user.

This means Gemini is **not** being given raw database access. The PHP layer decides what live data to expose and packages it into a controlled context first.

## 4. Decision Rules

### 4.1 Greeting handling

Short greetings such as `hello`, `hi`, or `good morning` return a friendly support introduction instead of a database dump.

### 4.2 Dataset-first handling

If the message matches a strong built-in pattern, the assistant responds from a known route immediately.

Examples:

- `room availability`
- `available rooms`
- `room types`
- `room prices`
- `hotel history`
- `check-in time`
- `wifi password`

### 4.3 Admin statistics handling

If the admin asks for statistics, analytics, summary, metrics, or similar wording, the assistant takes the statistics route and returns:

- current dashboard counts
- room inventory totals
- confirmed revenue for the selected range
- occupancy rate for the selected range
- reservation trend totals
- top room type by revenue
- monthly performance snapshot
- optional today-only numbers if the question mentions today

### 4.4 Admin operations handling

If the admin asks about reservations, payments, alerts, room status, or occupancy, the assistant returns operational data directly from the database.

### 4.5 Customer room handling

If the customer asks about room availability, room types, or room prices, the assistant returns a table-style database response instead of a generic AI answer.

### 4.6 Gemini fallback

If the question is not covered by a known dataset rule or DB-backed route, the assistant returns `kind = ai`. The API then:

- adds the current scoped instruction
- adds the live context prepared by PHP
- adds the recent conversation history
- adds extracted keywords
- calls Gemini

The returned answer is then shown to the user.

## 5. What The Local Assistant Can Answer Directly

### Customer side

- Available rooms
- Room numbers
- Room types
- Room prices
- Room inclusions
- Hotel founding / background
- Common policy questions such as check-in, check-out, WiFi, breakfast, parking, pets, cancellation, and room service

### Admin side

- Dashboard summary cards
- Sales and revenue by date range
- Occupancy rate
- Reservation counts
- Operational alerts
- Room inventory by type
- Top room type by revenue
- Table-style report summaries

## 6. How Table Responses Work

Room-related answers are intentionally formatted as markdown tables in the PHP layer.

Examples:

- room availability -> room number, type, floor, price
- room types -> room type, total rooms, available rooms, lowest price
- room prices -> room type, lowest price, availability
- admin statistics -> room inventory and performance summary tables

The front-end widget also renders those markdown tables as HTML tables so they stay readable inside the chat bubble.

## 7. Widget UI Behavior

The widget has:

- a floating launcher button
- a compact chat panel
- a sidebar maximize/expand mode
- quick prompt pills
- a message area with markdown-table rendering
- a send button and input area

The sidebar mode is handled entirely on the client side by `public/assets/js/support-widget.js`.

## 8. Support Prompt Structure

`public/support/api.php` builds the Gemini prompt from:

- the support role (`admin` or `customer`)
- the user message
- the prepared live context from `SupportAssistant`
- extracted keywords
- recent conversation history

This makes Gemini behave like a scoped hotel support assistant instead of a generic chatbot.

## 9. Environment Variables

The integration expects at least:

- `GEMINI_API_KEY`
- `GEMINI_MODEL`

Useful related settings may also include:

- `APP_NAME`
- `APP_ENV`
- `APP_URL`
- database credentials

`GEMINI_API_KEY` is used only for the fallback AI request. The local database logic still works without it, but the chat will be less conversational when a question falls outside the built-in rules.

## 10. Security and Data Boundaries

Important boundaries:

- The widget does not expose the whole database to Gemini.
- Admin access is still role-checked in `public/support/api.php`.
- Database-backed answers come from specific model methods only.
- The assistant should not invent numbers if live data is available.
- Open-ended questions fall back to Gemini with scoped context rather than raw database access.

## 11. Recommended Extension Points

If more support intelligence is needed later, the best places to add it are:

- `SupportAssistant::datasetEntries()` for new FAQ or keyword routes
- `SupportAssistant::adminStatisticsReply()` for new admin statistics
- `SupportAssistant::customerAvailabilityReply()` / `customerRoomTypeReply()` / `customerRoomPriceReply()` for room-related layouts
- `public/support/api.php` for additional prompt shaping
- `public/assets/js/support-widget.js` for UI improvements

## 12. Summary

The AI support system is a hybrid assistant:

- **local PHP rules and database queries first**
- **Gemini second**

That keeps the assistant grounded in real hotel data while still allowing natural-language help when the question is broader than the database rules.
