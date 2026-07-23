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
                    ? '<i class="bi bi-moon-stars-fill fs-5" style="color: #6366f1;"></i>' 
                    : '<i class="bi bi-sun-fill fs-5 text-warning"></i>';
                btn.setAttribute('title', isLight ? 'Switch to Dark Mode' : 'Switch to Light Mode');
                btn.setAttribute('aria-label', isLight ? 'Switch to Dark Mode' : 'Switch to Light Mode');
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

    // Mobile Top Navigation Bar (< 992px)
    echo '<div class="admin-mobile-nav d-lg-none d-flex align-items-center justify-content-between p-3 shadow-sm" style="background: rgba(15, 23, 42, 0.96); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(212, 175, 55, 0.3); position: sticky; top: 0; z-index: 1030;">';
    echo '<div class="d-flex align-items-center gap-2">';
    echo '<button class="btn btn-sm btn-outline-warning rounded-circle p-0 d-inline-flex align-items-center justify-content-center" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebarMobile" aria-controls="adminSidebarMobile" style="width: 38px; height: 38px; color: #FFDF73; border-color: rgba(212, 175, 55, 0.4); background: rgba(15, 23, 42, 0.85);"><i class="bi bi-list fs-4 text-warning"></i></button>';
    echo '<a class="d-flex align-items-center gap-2 text-decoration-none" href="dashboard.php">';
    echo '<img src="../assets/images/branding/emperors-hotel-logo.svg" alt="Emperor Hotel" style="width: 28px; height: 28px;">';
    echo '<span class="font-serif fw-bold text-gold-gradient small text-uppercase tracking-wider d-none d-sm-inline">Emperor Admin</span>';
    echo '</a>';
    echo '</div>';
    echo '<div class="d-flex align-items-center gap-2">';
    echo '<button type="button" class="btn btn-sm btn-outline-warning theme-toggle-btn rounded-circle me-1 d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px; padding: 0;" onclick="toggleEmperorTheme()" title="Switch Theme"><i class="bi bi-sun-fill fs-5"></i></button>';
    echo '<div class="dropdown position-relative" id="adminNotifDropdownMobile">';
    echo '<button class="btn btn-outline-warning rounded-circle p-0 position-relative shadow-sm d-inline-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 38px; height: 38px; color: #FFDF73; border-color: rgba(212, 175, 55, 0.4); background: rgba(15, 23, 42, 0.85);">';
    echo '<i class="bi bi-bell-fill fs-6"></i>';
    echo '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger fw-bold" id="adminNotifBadgeMobile" style="display: none; font-size: 0.65rem; border: 2px solid #020617;">0</span>';
    echo '</button>';
    echo '<ul class="dropdown-menu dropdown-menu-end shadow-lg rounded-4 p-3 admin-notif-dropdown" style="width: 320px; max-height: 400px; overflow-y: auto;" id="adminNotifListMobile">';
    echo '<li class="dropdown-header text-uppercase tracking-wider font-serif fw-bold px-0 pb-2 mb-2 border-bottom border-secondary d-flex align-items-center justify-content-between text-warning">';
    echo '<span><i class="bi bi-bell-fill me-2"></i>Notifications</span>';
    echo '<span class="badge bg-gold text-dark font-sans fw-bold text-xs" id="adminNotifHeaderBadgeMobile">0 Pending</span>';
    echo '</li>';
    echo '<div id="adminNotifItemsMobile" class="d-flex flex-column gap-2">';
    echo '<li class="text-center py-3 text-muted small"><i class="bi bi-check2-circle me-1 text-success"></i>No unread notifications</li>';
    echo '</div>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Mobile Navigation Slide-Out Offcanvas Drawer
    echo '<div class="offcanvas offcanvas-start bg-dark text-white border-end border-warning-subtle" id="adminSidebarMobile" tabindex="-1" aria-labelledby="adminSidebarMobileLabel" style="max-width: 280px; background: #020617 !important;">';
    echo '<div class="offcanvas-header border-bottom border-secondary pb-3">';
    echo '<div>';
    echo '<p class="eyebrow mb-1 text-warning">Emperor Hotel</p>';
    echo '<h5 class="offcanvas-title font-serif fw-bold" id="adminSidebarMobileLabel">Admin Panel</h5>';
    echo '</div>';
    echo '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>';
    echo '</div>';
    echo '<div class="offcanvas-body d-flex flex-column justify-content-between p-3">';
    echo '<div>';
    echo '<div class="profile-card mb-3 p-3 rounded-3" style="background: rgba(15, 23, 42, 0.9); border: 1px solid rgba(212, 175, 55, 0.2);">';
    echo '<div class="small text-uppercase text-muted">Signed in as</div>';
    echo '<div class="fw-semibold text-white">' . e($user['full_name']) . '</div>';
    echo '<div class="small text-warning fw-bold">' . e(ucfirst($user['role'])) . '</div>';
    echo '</div>';
    echo '<nav class="nav flex-column gap-2">';
    foreach ($links as $key => $link) {
        $isActive = $active === $key ? ' active' : '';
        echo '<a class="sidebar-link' . $isActive . ' text-decoration-none px-3 py-2.5 rounded-3 d-flex align-items-center gap-2" href="' . e($link['href']) . '">';
        echo '<i class="bi ' . e($link['icon']) . '"></i>';
        echo '<span>' . e($link['label']) . '</span>';
        echo '</a>';
    }
    echo '</nav>';
    echo '</div>';
    echo '<div class="d-grid gap-2 pt-3 border-top border-secondary mt-4">';
    echo '<a class="btn btn-warning btn-sm fw-semibold" href="../site/home.php"><i class="bi bi-house-door-fill me-2"></i>Home</a>';
    echo '<a class="btn btn-outline-light btn-sm" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Log Out</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // App Shell Container
    echo '<div class="app-shell">';
    
    // Desktop Sidebar (Visible >= 992px)
    echo '<aside class="sidebar-panel d-none d-lg-flex">';
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

    // Main Content Panel
    echo '<main class="content-panel">';
    echo '<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">';
    echo '<div><p class="eyebrow mb-1">Hotel Reservation System</p><h2 class="page-title mb-0">' . e($title) . '</h2></div>';
    echo '<div class="d-none d-lg-flex align-items-center gap-3">';
    echo '<button type="button" class="btn btn-outline-warning btn-sm theme-toggle-btn rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px; padding: 0;" onclick="toggleEmperorTheme()" title="Switch Theme"><i class="bi bi-sun-fill fs-5"></i></button>';
    echo '<div class="dropdown position-relative" id="adminNotifDropdown">';
    echo '<button class="btn btn-outline-warning rounded-circle p-2 position-relative shadow-sm d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 44px; height: 44px; color: #FFDF73; border-color: rgba(212, 175, 55, 0.4); background: rgba(15, 23, 42, 0.85);">';
    echo '<i class="bi bi-bell-fill fs-5"></i>';
    echo '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger fw-bold" id="adminNotifBadge" style="display: none; font-size: 0.7rem; border: 2px solid #020617;">0</span>';
    echo '</button>';
    echo '<ul class="dropdown-menu dropdown-menu-end shadow-lg rounded-4 p-3 admin-notif-dropdown" style="width: 360px; max-height: 480px; overflow-y: auto;" id="adminNotifList">';
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

    const handleDynamicFetch = (url) => {
        const contentPanel = document.querySelector(".content-panel") || document.body;
        
        contentPanel.style.opacity = "0.5";
        contentPanel.style.transition = "opacity 0.15s ease";

        fetch(url, {
            headers: { "X-Requested-With": "XMLHttpRequest" }
        })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, "text/html");

            const oldPanels = document.querySelectorAll(".content-panel > section, .content-panel > div");
            const newPanels = doc.querySelectorAll(".content-panel > section, .content-panel > div");

            if (oldPanels.length && newPanels.length && oldPanels.length === newPanels.length) {
                oldPanels.forEach((oldP, idx) => {
                    if (newPanels[idx]) {
                        oldP.replaceWith(newPanels[idx]);
                    }
                });
            } else {
                const newContent = doc.querySelector(".content-panel");
                if (newContent) {
                    contentPanel.innerHTML = newContent.innerHTML;
                }
            }

            window.history.pushState(null, "", url);
        })
        .catch(err => {
            console.error("AJAX filter error, falling back to page reload:", err);
            window.location.href = url;
        })
        .finally(() => {
            contentPanel.style.opacity = "1";
            attachDynamicEvents();
        });
    };

    const attachDynamicEvents = () => {
        document.querySelectorAll('form[method="get"]').forEach(form => {
            if (form.dataset.ajaxAttached) return;
            form.dataset.ajaxAttached = "true";

            form.addEventListener("submit", (e) => {
                e.preventDefault();
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);
                const actionUrl = form.getAttribute("action") || window.location.pathname;
                const fullUrl = actionUrl + "?" + params.toString();
                handleDynamicFetch(fullUrl);
            });

            form.querySelectorAll("select").forEach(select => {
                select.onchange = null;
                select.addEventListener("change", () => {
                    form.dispatchEvent(new Event("submit", { cancelable: true }));
                });
            });

            form.querySelectorAll('input[type="text"], input[type="search"]').forEach(input => {
                let debounceTimer;
                input.addEventListener("input", () => {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        form.dispatchEvent(new Event("submit", { cancelable: true }));
                    }, 350);
                });
            });
        });

        document.querySelectorAll('.pagination-container a, .pagination a, a[title="Reset Filters"], a[href="rooms.php"], a[href="booking-records.php"], a[href="reservations.php"], a[href="payments.php"]').forEach(link => {
            if (link.dataset.ajaxAttached) return;
            link.dataset.ajaxAttached = "true";

            link.addEventListener("click", (e) => {
                const href = link.getAttribute("href");
                if (href && !href.startsWith("#") && !href.startsWith("javascript:")) {
                    e.preventDefault();
                    handleDynamicFetch(href);
                }
            });
        });
    };

    attachDynamicEvents();

    window.addEventListener("popstate", () => {
        handleDynamicFetch(window.location.href);
    });
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
    echo '<button type="button" class="btn btn-outline-warning btn-sm theme-toggle-btn rounded-circle d-flex align-items-center justify-content-center me-2 shadow-sm" style="width: 38px; height: 38px; padding: 0;" onclick="toggleEmperorTheme()" title="Switch to Light Mode" aria-label="Switch to Light Mode"><i class="bi bi-sun-fill fs-5"></i></button>';

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

function renderPaginationControl(int $totalItems, int $currentPage, int $perPage, string $baseUrl = '', array $extraParams = []): void
{
    if ($totalItems <= 0) {
        return;
    }

    $totalPages = max(1, (int) ceil($totalItems / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $startItem = ($currentPage - 1) * $perPage + 1;
    $endItem = min($totalItems, $currentPage * $perPage);

    $buildUrl = function(int $page) use ($extraParams, $perPage): string {
        $queryParams = array_merge($_GET, $extraParams, ['page' => $page, 'per_page' => $perPage]);
        unset($queryParams['action']);
        return '?' . http_build_query($queryParams);
    };

    echo '<div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4 pt-3 border-top border-secondary border-opacity-25 pagination-container">';
    echo '  <div class="small text-muted mb-0">';
    echo '      Showing <span class="fw-bold pagination-item-count text-body">' . number_format($startItem) . '</span> to <span class="fw-bold pagination-item-count text-body">' . number_format($endItem) . '</span> of <span class="fw-bold text-warning">' . number_format($totalItems) . '</span> entries';
    echo '  </div>';

    if ($totalPages > 1) {
        echo '  <nav aria-label="Page navigation">';
        echo '      <ul class="pagination pagination-sm mb-0 flex-wrap gap-1">';

        if ($currentPage > 1) {
            echo '          <li class="page-item"><a class="page-link bg-dark text-light border-secondary" href="' . e($buildUrl(1)) . '" title="First Page">&laquo;</a></li>';
            echo '          <li class="page-item"><a class="page-link bg-dark text-light border-secondary" href="' . e($buildUrl($currentPage - 1)) . '" title="Previous Page">&lsaquo; Prev</a></li>';
        } else {
            echo '          <li class="page-item disabled"><span class="page-link bg-dark text-muted border-secondary">&laquo;</span></li>';
            echo '          <li class="page-item disabled"><span class="page-link bg-dark text-muted border-secondary">&lsaquo; Prev</span></li>';
        }

        $window = 2;
        $startPage = max(1, $currentPage - $window);
        $endPage = min($totalPages, $currentPage + $window);

        if ($startPage > 1) {
            echo '          <li class="page-item"><a class="page-link bg-dark text-light border-secondary" href="' . e($buildUrl(1)) . '">1</a></li>';
            if ($startPage > 2) {
                echo '          <li class="page-item disabled"><span class="page-link bg-dark text-muted border-secondary">&hellip;</span></li>';
            }
        }

        for ($p = $startPage; $p <= $endPage; $p++) {
            if ($p === $currentPage) {
                echo '          <li class="page-item active"><span class="page-link bg-warning text-dark border-warning fw-bold">' . $p . '</span></li>';
            } else {
                echo '          <li class="page-item"><a class="page-link bg-dark text-light border-secondary" href="' . e($buildUrl($p)) . '">' . $p . '</a></li>';
            }
        }

        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                echo '          <li class="page-item disabled"><span class="page-link bg-dark text-muted border-secondary">&hellip;</span></li>';
            }
            echo '          <li class="page-item"><a class="page-link bg-dark text-light border-secondary" href="' . e($buildUrl($totalPages)) . '">' . $totalPages . '</a></li>';
        }

        if ($currentPage < $totalPages) {
            echo '          <li class="page-item"><a class="page-link bg-dark text-light border-secondary" href="' . e($buildUrl($currentPage + 1)) . '" title="Next Page">Next &rsaquo;</a></li>';
            echo '          <li class="page-item"><a class="page-link bg-dark text-light border-secondary" href="' . e($buildUrl($totalPages)) . '" title="Last Page">&raquo;</a></li>';
        } else {
            echo '          <li class="page-item disabled"><span class="page-link bg-dark text-muted border-secondary">Next &rsaquo;</span></li>';
            echo '          <li class="page-item disabled"><span class="page-link bg-dark text-muted border-secondary">&raquo;</span></li>';
        }

        echo '      </ul>';
        echo '  </nav>';
    }

    echo '</div>';
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
