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
    <link rel="shortcut icon" type="image/svg+xml" href="../assets/images/branding/emperors-hotel-logo.svg">
    <link href="../assets/css/app.css" rel="stylesheet">
    {$extraStylesheetLinks}
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

        echo '<div class="alert alert-' . e($type) . ' mb-4">' . e($message['message']) . '</div>';
    }
}

function renderAdminLayoutStart(string $title, string $active, array $user, array $extraStylesheets = []): void
{
    renderHeader($title, $extraStylesheets, 'admin-page admin-page--' . $active);

    $links = [
        'dashboard' => ['label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'bi-speedometer2'],
        'rooms' => ['label' => 'Rooms', 'href' => 'rooms.php', 'icon' => 'bi-door-open'],
        'reservations' => ['label' => 'Reservations', 'href' => 'reservations.php', 'icon' => 'bi-calendar-plus'],
        'booking-records' => ['label' => 'Booking Records', 'href' => 'booking-records.php', 'icon' => 'bi-journal-check'],
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
    echo '<div class="d-grid gap-2">';
    echo '<a class="btn btn-warning fw-semibold" href="../site/home.php">View Site</a>';
    echo '<a class="btn btn-outline-light" href="../auth/logout.php">Log Out</a>';
    echo '</div>';
    echo '</aside>';
    echo '<main class="content-panel">';
    echo '<div class="content-card">';
    echo '<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">';
    echo '<div><p class="eyebrow mb-1">Hotel Reservation System</p><h2 class="page-title mb-0">' . e($title) . '</h2></div>';
    echo '</div>';
    renderFlashBlock();
}

function renderAdminLayoutEnd(): void
{
    echo '</div>';
    echo '</main>';
    echo '</div>';
    renderSupportWidget('admin');
    echo '<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>';
    echo '<script src="../assets/js/support-widget.js" defer></script>';
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
    echo '<a class="btn btn-outline-warning btn-sm fw-bold font-serif" href="' . e($sitePrefix . 'rooms.php') . '"><i class="bi bi-grid-3x3-gap-fill me-1 text-warning"></i>Rooms Kiosk</a>';

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
