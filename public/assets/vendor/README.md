# Vendor Browser Assets

This folder stores third-party browser files used by the Emperor Hotel Reservation System. These files are committed locally so the XAMPP project can run without internet access during demos or development.

## Included Assets

| Asset | Version / Family | Main Files | Used For |
| --- | --- | --- | --- |
| Bootstrap | `5.3.3` | `bootstrap/css/bootstrap.min.css`, `bootstrap/js/bootstrap.bundle.min.js` | Layout, forms, buttons, tables, alerts, carousel behavior |
| Bootstrap Icons | `1.11.3` | `bootstrap-icons/bootstrap-icons.css`, `bootstrap-icons/fonts/*` | Admin and UI icons |
| Chart.js | `4.5.1` | `chartjs/chart.umd.min.js` | Dashboard charts |
| Google Fonts | DM Sans, DM Serif Display | `fonts/google-fonts.css`, `fonts/files/*.woff2` | Local typography |

## Notes

- Runtime pages should load these local files instead of CDN URLs.
- Bootstrap and Bootstrap Icons license files are stored inside their vendor folders.
- Chart.js license and source notes are stored in `chartjs/`.
- If a library is updated, update this README and `docs/technology-stack.md` at the same time.
