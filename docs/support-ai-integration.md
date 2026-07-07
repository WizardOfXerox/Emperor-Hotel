# AI Support Integration

This document explains the Emperor Hotel support widget that appears on the public site and admin pages.

## What It Does

- Answers customer questions about available rooms, room types, room prices, and hotel background.
- Answers admin questions about dashboard summaries, sales, occupancy, reservations, and date ranges.
- Uses live database-backed PHP model data first so the reply stays grounded in the system.
- Falls back to Gemini only when the request is too open-ended for the built-in dataset and DB rules.

## Response Style

- Room availability and room type requests are formatted as tables.
- Short greetings get a friendly welcome instead of dashboard dumps.
- Admin questions use dashboard context, reports, and operational data.
- Customer questions stay on guest-facing hotel information unless admin access is explicitly requested.

## Configuration Notes

- `GEMINI_API_KEY` stores the Gemini key used by the fallback AI request.
- `GEMINI_MODEL` controls which Gemini model the support API calls.
- The support API is routed through `public/support/api.php`.
- The widget UI is rendered by `public/assets/js/support-widget.js`.

## Safety Notes

- The support widget should not invent room counts, sales, or report values.
- Database-backed responses should always win when the question matches a known hotel support pattern.
- Open-ended questions are passed to Gemini with scoped context so the reply stays relevant.
