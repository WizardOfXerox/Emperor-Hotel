# 🖥️ WEB DEVICE EMULATION SCREEN RESOLUTIONS REFERENCE GUIDE

This guide lists the industry-standard CSS logical viewports and physical screen resolutions used in **Chrome DevTools**, **Playwright**, and **Figma** responsive device testing.

---

## 🖥️ 1. Desktop & Ultra-Wide Monitors

| Device / Monitor Category | Physical Resolution | DevTools / CSS Viewport | Aspect Ratio | Usage / Notes |
| :--- | :--- | :--- | :--- | :--- |
| **4K Ultra HD Monitor** | 3840 x 2160 | **3840 x 2160** *(or 1920x1080 @2x)* | 16:9 | High-DPI 27"–32" desktop displays |
| **Ultrawide Monitor** | 3440 x 1440 | **3440 x 1440** | 21:9 | 34" Curved ultrawide gaming/productivity |
| **2K / QHD Monitor** | 2560 x 1440 | **2560 x 1440** | 16:9 | Standard 27" desktop monitor target |
| **Full HD Desktop (1080p)** | 1920 x 1080 | **1920 x 1080** | 16:9 | **#1 Most common desktop display worldwide** |

---

## 💻 2. Laptop & Notebook Displays

| Device / Laptop Category | Physical Resolution | DevTools / CSS Viewport | Aspect Ratio | Usage / Notes |
| :--- | :--- | :--- | :--- | :--- |
| **MacBook Pro 16"** | 3456 x 2234 | **1728 x 1117** | 16:10 | Apple Retina @2x scaling |
| **MacBook Pro 14"** | 3024 x 1964 | **1512 x 982** | 16:10 | Apple Retina @2x scaling |
| **MacBook Air 13"** | 2560 x 1600 | **1440 x 900** | 16:10 | Standard 13" Apple laptop target |
| **Windows HD Laptop (125%)**| 1920 x 1080 | **1536 x 864** | 16:9 | 15.6" Windows default 125% OS scaling |
| **Windows Budget Laptop** | 1366 x 768 | **1366 x 768** | 16:9 | **Most common budget/enterprise laptop** |
| **Compact Notebook** | 1280 x 800 | **1280 x 800** | 16:10 | Older 13" laptops & Chromebooks |

---

## 📱 3. Tablets & 2-in-1 Devices

| Device Model | Orientation | CSS Viewport | Aspect Ratio | Device Pixel Ratio |
| :--- | :--- | :--- | :--- | :--- |
| **iPad Pro 12.9"** | Portrait | **1024 x 1366** | 3:4 | 2.0 |
| **iPad Pro 12.9"** | Landscape | **1366 x 1024** | 4:3 | 2.0 |
| **iPad Air / iPad 10th Gen** | Portrait | **820 x 1180** | 3:4 | 2.0 |
| **iPad Air / iPad 10th Gen** | Landscape | **1180 x 820** | 4:3 | 2.0 |
| **iPad Mini** | Portrait | **768 x 1024** | 3:4 | 2.0 |
| **Surface Pro 8 / 9** | Landscape | **1368 x 912** | 3:2 | 2.0 |
| **Samsung Galaxy Tab S9** | Landscape | **1280 x 800** | 16:10 | 2.0 |

---

## 📱 4. Smartphones (Mobile Viewports)

| Device Model | CSS Viewport (W x H) | Screen Category | Device Pixel Ratio |
| :--- | :--- | :--- | :--- |
| **iPhone 15 Pro Max / 14 Pro Max** | **430 x 932** | Large iOS Flagship | 3.0 |
| **iPhone 15 / 15 Pro / 14 / 13** | **390 x 844** | **Standard iOS Target** | 3.0 |
| **iPhone 13 mini / 12 mini** | **375 x 812** | Compact iOS | 3.0 |
| **iPhone SE (3rd Gen) / 8** | **375 x 667** | Older iOS Button Layout | 2.0 |
| **Samsung Galaxy S23 Ultra** | **412 x 915** | Large Android Flagship | 3.5 |
| **Samsung Galaxy S22 / S21** | **360 x 800** | **Standard Android Target** | 3.0 |
| **Google Pixel 7 / 8** | **412 x 915** | Standard Pixel Target | 2.625 |
| **Budget Android / KaiOS** | **320 x 568** | Minimum Web Baseline | 1.5 |

---

## 🎯 Recommended Breakpoint Thresholds for Media Queries

When writing CSS media queries, use these standard breakpoint boundaries:

```css
/* Mobile Devices */
@media (max-width: 575.98px) { ... }

/* Small Tablets / Landscape Mobile */
@media (min-width: 576px) and (max-width: 767.98px) { ... }

/* Tablets / iPad Portrait */
@media (min-width: 768px) and (max-width: 991.98px) { ... }

/* Laptops / Smaller Desktops (13"-15") */
@media (min-width: 992px) and (max-width: 1199.98px) { ... }

/* Standard Desktop Monitors (24"-27") */
@media (min-width: 1200px) and (max-width: 1399.98px) { ... }

/* Large Desktop Monitors (27"+ / 1440p / 4K) */
@media (min-width: 1400px) { ... }
```
