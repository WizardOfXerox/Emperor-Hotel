<?php

declare(strict_types=1);

function renderHeader(string $title, array $extraStylesheets = [], string $bodyClass = ''): void
{
    $extraStylesheets[] = '../assets/css/support-widget.css';
    $extraStylesheetLinks = '';

    foreach ($extraStylesheets as $stylesheet) {
        $extraStylesheetLinks .= '<link href="' . e($stylesheet) . '" rel="stylesheet">' . PHP_EOL;
    }

    $bodyClassAttribute = $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : '';

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <link href="../assets/vendor/fonts/google-fonts.css" rel="stylesheet">
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="../assets/images/branding/emperors-hotel-logo.svg">
    <link rel="alternate icon" type="image/png" href="../assets/images/branding/emperors-hotel-logo.svg">
    <link rel="shortcut icon" href="../favicon.ico">
    <link rel="apple-touch-icon" href="../assets/images/branding/emperors-hotel-logo.svg">
    <link href="../assets/css/app.css" rel="stylesheet">
    {$extraStylesheetLinks}
    <script>
        (function() {
            if (localStorage.getItem('emperor_theme') === 'light') {
                document.documentElement.classList.add('light-mode');
            }
        })();
        function toggleEmperorTheme() {
            const isLight = document.documentElement.classList.toggle('light-mode');
            if (document.body) {
                document.body.classList.toggle('light-mode', isLight);
            }
            localStorage.setItem('emperor_theme', isLight ? 'light' : 'dark');
            updateThemeToggleButtons(isLight);
        }
        function updateThemeToggleButtons(isLight) {
            document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
                btn.innerHTML = isLight 
                    ? '<i class="bi bi-moon-stars-fill me-1 text-primary"></i> Dark Mode' 
                    : '<i class="bi bi-sun-fill me-1 text-warning"></i> Light Mode';
                btn.classList.toggle('btn-outline-dark', isLight);
                btn.classList.toggle('btn-outline-warning', !isLight);
            });
        }
        document.addEventListener('DOMContentLoaded', () => {
            const isLight = localStorage.getItem('emperor_theme') === 'light';
            if (isLight) {
                document.documentElement.classList.add('light-mode');
                document.body.classList.add('light-mode');
            }
            updateThemeToggleButtons(isLight);
        });
    </script>
</head>
<body{$bodyClassAttribute}>
HTML;
}

function renderFlashBlock(): void
{
    foreach (getFlashMessages() as $message) {
        $type = match ($message['type']) {
            'success' => 'success',
            'error' => 'danger',
            'warning' => 'warning',
            default => 'info',
        };

        echo '<div class="alert alert-' . e($type) . ' alert-dismissible fade show mb-4 shadow-sm" role="alert">' 
            . e($message['message']) 
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            . '</div>';
    }
}

function renderAdminLayoutStart(string $title, string $active, array $user, array $extraStylesheets = []): void
{
    renderHeader($title, $extraStylesheets, 'admin-page admin-page--' . $active);

    $links = [
        'dashboard' => ['label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'bi-speedometer2'],
        'create-reservation' => ['label' => 'Create Reservation', 'href' => 'create-reservation.php', 'icon' => 'bi-calendar-plus'],
        'reservations' => ['label' => 'Reservations', 'href' => 'reservations.php', 'icon' => 'bi-calendar-check'],
        'booking-records' => ['label' => 'Booking Logs', 'href' => 'booking-records.php', 'icon' => 'bi-clock-history'],
        'rooms' => ['label' => 'Rooms', 'href' => 'rooms.php', 'icon' => 'bi-door-open'],
        'payments' => ['label' => 'Payments', 'href' => 'payments.php', 'icon' => 'bi-credit-card-2-back'],
        'guests' => ['label' => 'Guests', 'href' => 'guests.php', 'icon' => 'bi-person-lines-fill'],
        'reports' => ['label' => 'Reports', 'href' => 'reports.php', 'icon' => 'bi-graph-up-arrow'],
        'users' => ['label' => 'Users', 'href' => 'users.php', 'icon' => 'bi-people'],
    ];

    echo '<div class="app-shell">';
    echo '<aside class="sidebar-panel">';
    echo '<div>';
    echo '<div class="brand-block">';
    echo '<p class="eyebrow mb-2">Emperor Hotel</p>';
    echo '<h1 class="h4 mb-1">Admin Panel</h1>';
    echo '<p class="text-light opacity-75 mb-0">Manage hotel operations and records.</p>';
    echo '</div>';
    echo '<div class="profile-card">';
    echo '<div class="small text-uppercase text-muted">Signed in as</div>';
    echo '<div class="fw-semibold">' . e($user['full_name']) . '</div>';
    echo '<div class="small text-warning">' . e(ucfirst($user['role'])) . '</div>';
    echo '</div>';
    echo '<nav class="nav flex-column gap-2">';

    foreach ($links as $key => $link) {
        $isActive = $active === $key ? ' active' : '';
        echo '<a class="sidebar-link' . $isActive . '" href="' . e($link['href']) . '">';
        echo '<i class="bi ' . e($link['icon']) . '"></i>';
        echo '<span>' . e($link['label']) . '</span>';
        echo '</a>';
    }

    echo '</nav>';
    echo '</div>';
    echo '<div class="sidebar-actions d-grid gap-2 mt-auto pt-3 border-top border-secondary-subtle" style="position: sticky; bottom: 0; background: #020617; z-index: 5; margin-top: auto; padding-bottom: 0.5rem;">';
    echo '<a class="btn btn-warning fw-semibold" href="../site/home.php"><i class="bi bi-house-door-fill me-2"></i>Home</a>';
    echo '<a class="btn btn-outline-light" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Log Out</a>';
    echo '</div>';
    echo '</aside>';
    echo '<main class="content-panel">';
    echo '<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">';
    echo '<div><p class="eyebrow mb-1">Hotel Reservation System</p><h2 class="page-title mb-0">' . e($title) . '</h2></div>';
    echo '<div class="d-flex align-items-center gap-3">';
    echo '<button type="button" class="btn btn-outline-warning btn-sm fw-bold theme-toggle-btn rounded-pill px-3 py-2 d-flex align-items-center shadow-sm" onclick="toggleEmperorTheme()"><i class="bi bi-sun-fill me-1"></i> Light Mode</button>';
    echo '<div class="dropdown position-relative" id="adminNotifDropdown">';
    echo '<button class="btn btn-outline-warning rounded-circle p-2 position-relative shadow-sm d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 44px; height: 44px; color: #FFDF73; border-color: rgba(212, 175, 55, 0.4); background: rgba(15, 23, 42, 0.85);">';
    echo '<i class="bi bi-bell-fill fs-5"></i>';
    echo '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger fw-bold" id="adminNotifBadge" style="display: none; font-size: 0.7rem; border: 2px solid #020617;">0</span>';
    echo '</button>';
    echo '<ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg rounded-4 p-3" style="width: 360px; max-height: 480px; overflow-y: auto; background: rgba(15, 23, 42, 0.98); border: 1px solid rgba(212, 175, 55, 0.45);" id="adminNotifList">';
    echo '<li class="dropdown-header text-uppercase tracking-wider font-serif fw-bold px-0 pb-2 mb-2 border-bottom border-secondary d-flex align-items-center justify-content-between text-warning">';
    echo '<span><i class="bi bi-bell-fill me-2"></i>New Reservations</span>';
    echo '<span class="badge bg-gold text-dark font-sans fw-bold text-xs" id="adminNotifHeaderBadge">0 Pending</span>';
    echo '</li>';
    echo '<div id="adminNotifItems" class="d-flex flex-column gap-2">';
    echo '<li class="text-center py-3 text-muted small"><i class="bi bi-check2-circle me-1 text-success"></i>No unread new reservations</li>';
    echo '</div>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    renderFlashBlock();
}

function renderAdminLayoutEnd(): void
{
    echo '</main>';
    echo '</div>';
    renderSupportWidget('admin');
    echo '<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>';
    echo '<script src="../assets/js/support-widget.js" defer></script>';
    echo '<script src="../assets/js/admin-notifications.js" defer></script>';
    echo <<<'HTML'
<script>
document.addEventListener("DOMContentLoaded", () => {
    setTimeout(() => {
        document.querySelectorAll(".alert").forEach((alertEl) => {
            alertEl.classList.remove("show");
            alertEl.classList.add("fade");
            setTimeout(() => {
                if (alertEl && alertEl.parentNode) {
                    alertEl.parentNode.removeChild(alertEl);
                }
            }, 400);
        });
    }, 3000);
});
</script>
HTML;
    echo '</body></html>';
}

function renderSiteLayoutStart(string $title, ?array $user = null, string $sitePrefix = '', array $extraStylesheets = []): void
{
    renderHeader($title, $extraStylesheets);

    echo '<nav class="site-nav">';
    echo '<div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">';
    echo '<a class="brand-link d-inline-flex align-items-center gap-3" href="' . e($sitePrefix . 'home.php') . '">';
    echo '<img class="brand-logo" src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel logo">';
    echo '<span>Emperor Hotel</span>';
    echo '</a>';
    echo '<div class="d-flex flex-wrap align-items-center gap-2">';
    echo '<a class="btn btn-outline-light btn-sm" href="' . e($sitePrefix . 'home.php') . '">Home</a>';
    echo '<a class="btn btn-outline-warning btn-sm fw-bold font-serif" href="' . e($sitePrefix . 'rooms.php') . '"><i class="bi bi-door-open-fill me-1 text-warning"></i>Rooms</a>';
    echo '<a class="btn btn-outline-light btn-sm" href="' . e($sitePrefix . 'suites.php') . '">Suites</a>';
    echo '<button type="button" class="btn btn-outline-warning btn-sm fw-bold theme-toggle-btn rounded-pill px-3 py-1 d-flex align-items-center shadow-sm" onclick="toggleEmperorTheme()"><i class="bi bi-sun-fill me-1"></i> Light Mode</button>';

    if ($user) {
        if ($user['role'] === 'admin') {
            echo '<a class="btn btn-warning btn-sm fw-semibold" href="../admin/dashboard.php">Admin Dashboard</a>';
        } else {
            echo '<a class="btn btn-warning btn-sm fw-semibold" href="../user/dashboard.php">My Dashboard</a>';
        }

        echo '<a class="btn btn-outline-light btn-sm" href="../auth/logout.php">Log Out</a>';
    } else {
        echo '<a class="btn btn-warning btn-sm fw-semibold" href="../auth/login.php">Log In</a>';
        echo '<a class="btn btn-outline-light btn-sm" href="../auth/register.php">Register</a>';
    }

    echo '</div>';
    echo '</div>';
    echo '</nav>';
    echo '<main class="site-main">';
    echo '<div class="container py-4">';
    renderFlashBlock();
}

function renderSiteLayoutEnd(): void
{
    echo '</div>';
    echo '</main>';
    renderSupportWidget('customer');
    echo '<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>';
    echo '<script src="../assets/js/support-widget.js" defer></script>';
    echo <<<'HTML'
<script>
document.addEventListener("DOMContentLoaded", () => {
    setTimeout(() => {
        document.querySelectorAll(".alert").forEach((alertEl) => {
            alertEl.classList.remove("show");
            alertEl.classList.add("fade");
            setTimeout(() => {
                if (alertEl && alertEl.parentNode) {
                    alertEl.parentNode.removeChild(alertEl);
                }
            }, 400);
        });
    }, 3000);
});
</script>
HTML;
    echo '</body></html>';
}

function renderSupportWidget(string $scope): void
{
    $scope = in_array($scope, ['admin', 'customer'], true) ? $scope : 'customer';
    $title = $scope === 'admin' ? 'Admin Support' : 'Customer Support';
    $subtitle = $scope === 'admin'
        ? 'Reads dashboard, sales, and operations data'
        : 'Reads room availability and hotel info';
    $welcome = $scope === 'admin'
        ? 'Hello admin. Ask about monthly sales, date ranges, occupancy, or dashboard data.'
        : 'Hello. Ask about available rooms, prices, room types, or hotel history.';
    $hint = $scope === 'admin'
        ? 'Try: "monthly sales this month" or "revenue from 2026-06-01 to 2026-06-30"'
        : 'Try: "show available rooms" or "what are the room prices?"';

    echo '<div data-support-widget data-support-api="../support/api.php" data-support-scope="' . e($scope) . '" data-support-title="' . e($title) . '" data-support-subtitle="' . e($subtitle) . '" data-support-welcome="' . e($welcome) . '" data-support-hint="' . e($hint) . '"></div>';
}
