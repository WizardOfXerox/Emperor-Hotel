(function () {
    'use strict';

    const notifBadge = document.getElementById('adminNotifBadge');
    const notifHeaderBadge = document.getElementById('adminNotifHeaderBadge');
    const notifItems = document.getElementById('adminNotifItems');

    if (!notifBadge || !notifItems) {
        return;
    }

    let knownReservationIds = new Set();
    let initialLoadComplete = false;

    function playAlertChime() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.3); // A5
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.5);
        } catch (e) {
            // AudioContext autoplay restricted fallback
        }
    }

    function showToastAlert(n) {
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-dark border border-warning shadow-lg show position-fixed bottom-0 end-0 m-4';
        toast.style.zIndex = '9999';
        toast.style.borderRadius = '14px';
        toast.style.background = 'rgba(15, 23, 42, 0.95)';
        toast.style.backdropFilter = 'blur(15px)';
        toast.style.border = '1px solid #D4AF37';

        toast.innerHTML = `
            <div class="d-flex p-3">
                <div class="toast-body d-flex align-items-center gap-3">
                    <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background: rgba(212, 175, 55, 0.2); color: #FFDF73; width: 42px; height: 42px;">
                        <i class="bi bi-bell-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="m-0 font-serif fw-bold text-warning">🔔 New Reservation Received!</h6>
                        <p class="m-0 small text-light fw-semibold">${escapeHtml(n.guest_name)} booked <strong>${escapeHtml(n.room_type)}</strong> (#${escapeHtml(n.room_number)}) &bull; <span class="text-gold">${escapeHtml(n.amount)}</span></p>
                        <small class="text-muted">${escapeHtml(n.time_ago)}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        document.body.appendChild(toast);
        playAlertChime();

        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.remove();
            }
        }, 8000);
    }

    function escapeHtml(val) {
        return String(val)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    async function checkNotifications() {
        try {
            const res = await fetch('../admin/api_notifications.php');
            if (!res.ok) return;

            const data = await res.json();
            if (!data.ok) return;

            const notifications = data.notifications || [];
            const pendingCount = data.pending_count || 0;

            // Update badges
            if (pendingCount > 0) {
                notifBadge.textContent = pendingCount;
                notifBadge.style.display = 'inline-block';
                if (notifHeaderBadge) notifHeaderBadge.textContent = pendingCount + ' Pending';
            } else {
                notifBadge.style.display = 'none';
                if (notifHeaderBadge) notifHeaderBadge.textContent = '0 Pending';
            }

            // Render list
            if (notifications.length === 0) {
                notifItems.innerHTML = '<li class="text-center py-3 text-muted small"><i class="bi bi-check2-circle me-1 text-success"></i>No new reservations</li>';
                return;
            }

            let listHtml = '';
            let newReservationsFound = [];

            notifications.forEach(n => {
                const isUnseen = !knownReservationIds.has(n.reservation_id);
                if (isUnseen) {
                    knownReservationIds.add(n.reservation_id);
                    if (initialLoadComplete && n.is_new) {
                        newReservationsFound.push(n);
                    }
                }

                const badgeBg = n.status === 'Pending' ? 'bg-warning text-dark' : (n.status === 'Conflict' ? 'bg-danger text-white' : 'bg-success text-white');

                listHtml += `
                    <a href="../admin/reservations.php?search=${encodeURIComponent(n.guest_name)}" class="dropdown-item notif-item-card p-3 rounded-3 d-flex align-items-center justify-content-between gap-2 text-wrap text-decoration-none mb-1">
                        <div>
                            <div class="fw-bold font-serif notif-guest-name small"><i class="bi bi-door-closed me-1"></i>${escapeHtml(n.guest_name)}</div>
                            <div class="text-xs notif-details mt-1">${escapeHtml(n.room_type)} (#${escapeHtml(n.room_number)}) &bull; <strong>${escapeHtml(n.amount)}</strong></div>
                            <div class="text-xs notif-meta mt-1">${escapeHtml(n.check_in)} &rarr; ${escapeHtml(n.check_out)}</div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <span class="badge ${badgeBg} text-xs px-2 py-1 mb-1 d-block">${escapeHtml(n.status)}</span>
                            <small class="notif-meta text-xs d-block">${escapeHtml(n.time_ago)}</small>
                        </div>
                    </a>
                `;
            });

            notifItems.innerHTML = listHtml;

            // Trigger popup toast alerts for brand new reservations found after initial load
            if (initialLoadComplete && newReservationsFound.length > 0) {
                newReservationsFound.forEach(n => showToastAlert(n));
            }

            initialLoadComplete = true;

        } catch (e) {
            // Silence network errors
        }
    }

    checkNotifications();
    setInterval(checkNotifications, 15000);
})();
