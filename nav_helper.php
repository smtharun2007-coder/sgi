<?php
// nav_helper.php — centralized responsive navbar system for SGI

function renderNavMobileStyles() {
    static $rendered = false;
    if ($rendered) return '';
    $rendered = true;

    return <<<CSS
<style>
/* ========================================================
   SGI RESPONSIVE NAVBAR: DESKTOP CLEAN + MOBILE DRAWER
   ======================================================== */

/* Desktop defaults (> 768px): Exact classic SGI styling */
.navbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 30px;
    position: sticky;
    top: 0;
    z-index: 9999;
    box-sizing: border-box;
}

.nav-mobile-actions {
    display: none;
    align-items: center;
    gap: 12px;
}

.nav-hamburger {
    display: none;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 6px;
    flex-direction: column;
    gap: 5px;
    outline: none;
}

.nav-hamburger span {
    display: block;
    width: 24px;
    height: 2.5px;
    background: #ffffff;
    border-radius: 2px;
    transition: all 0.2s ease;
}

/* MOBILE VIEW (<= 768px) */
@media (max-width: 768px) {
    .navbar {
        padding: 10px 16px;
        position: sticky;
        top: 0;
        width: 100%;
        box-sizing: border-box;
    }

    .nav-brand {
        font-size: 19px !important;
    }

    .nav-brand .nav-logo {
        width: 28px !important;
        height: 28px !important;
    }

    /* Show mobile header actions: Bell + Hamburger */
    .nav-mobile-actions {
        display: flex !important;
    }

    .nav-hamburger {
        display: flex !important;
    }

    /* Hide desktop bell on mobile to avoid duplication */
    .nav-desktop-bell {
        display: none !important;
    }

    /* Turn .nav-links into a clean full-width slide drawer */
    .nav-links {
        display: none !important;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        width: 100%;
        background: #1a1a2e;
        flex-direction: column !important;
        align-items: stretch !important;
        padding: 14px 18px !important;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.55);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        gap: 6px !important;
        max-height: calc(100vh - 60px);
        overflow-y: auto;
        box-sizing: border-box;
        z-index: 10000;
    }

    .nav-links.mobile-open {
        display: flex !important;
        animation: navSlideDown 0.18s ease-out;
    }

    @keyframes navSlideDown {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .nav-links a {
        display: flex !important;
        align-items: center;
        gap: 8px;
        padding: 12px 14px !important;
        margin: 0 !important;
        font-size: 15px !important;
        color: #cbd5e1 !important;
        border-radius: 8px;
        text-decoration: none !important;
        background: transparent;
        transition: background 0.15s, color 0.15s;
    }

    .nav-links a:hover, .nav-links a.active {
        color: #ffffff !important;
        background: rgba(255, 255, 255, 0.08) !important;
    }

    .nav-dropdown {
        width: 100%;
        position: relative;
    }

    .nav-dropdown-btn {
        width: 100% !important;
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        padding: 12px 14px !important;
        font-size: 15px !important;
        color: #cbd5e1 !important;
        border-radius: 8px;
        background: transparent !important;
        cursor: pointer;
        outline: none;
        box-sizing: border-box;
    }

    .nav-dropdown-btn:hover {
        background: rgba(255, 255, 255, 0.08) !important;
        color: #fff !important;
    }

    .nav-dropdown-menu {
        position: static !important;
        display: none !important;
        width: 100% !important;
        box-shadow: none !important;
        border: none !important;
        background: rgba(0, 0, 0, 0.25) !important;
        border-radius: 8px !important;
        margin-top: 4px;
        padding: 4px 0 4px 12px !important;
    }

    .nav-dropdown.mobile-open .nav-dropdown-menu {
        display: block !important;
    }

    .nav-dropdown.mobile-open .nav-dropdown-btn .caret {
        transform: rotate(180deg);
    }

    .nav-dropdown-menu a {
        padding: 10px 14px !important;
        font-size: 14px !important;
        color: #cbd5e1 !important;
    }

    .nav-links .btn-logout {
        margin-top: 10px !important;
        justify-content: center !important;
        padding: 12px !important;
        font-weight: 700;
        text-align: center;
    }
}
</style>
<script>
function toggleMobileMenu() {
    var links = document.getElementById('sgiNavLinks');
    if (links) links.classList.toggle('mobile-open');
}

function toggleMobileDropdown(btn) {
    if (window.innerWidth <= 768) {
        var dropdown = btn.closest('.nav-dropdown');
        if (dropdown) dropdown.classList.toggle('mobile-open');
    }
}

// Close mobile drawer when clicking outside
document.addEventListener('click', function(e) {
    var navbar = document.querySelector('.navbar');
    var links = document.getElementById('sgiNavLinks');
    var hamburger = document.querySelector('.nav-hamburger');
    if (links && links.classList.contains('mobile-open')) {
        if (navbar && !navbar.contains(e.target)) {
            links.classList.remove('mobile-open');
        }
    }
});
</script>
CSS;
}

function renderStudentNavbar($active = '', $unreadCount = 0) {
    $current = basename($active ?: $_SERVER['PHP_SELF']);
    $badge = $unreadCount > 0 ? "<span class=\"notif-badge\">$unreadCount</span>" : '';
    $styles = renderNavMobileStyles();

    $isHome = ($current === 'dashboard.php');
    $isAcademics = in_array($current, ['academics.php', 'add_semester.php', 'delete_semester.php', 'edit_subjects.php', 'credit_subjects.php', 'cat1_marks.php', 'cat2_marks.php', 'cat3_marks.php', 'final_ca_marks.php', 'calculate_sgi.php', 'verify_marks.php', 'semester_detail.php']);
    $isAttendance = in_array($current, ['attendance.php', 'attendance_calendar.php', 'attendance_od_request.php']);
    $isMore = in_array($current, ['update_profile.php', 'about.php', 'contact.php', 'calendar.php', 'announcements.php']);

    $homeStyle = $isHome ? 'color:#fff;font-weight:700;' : '';
    $acadStyle = ($isAcademics || $isAttendance) ? 'color:#fff;font-weight:700;' : '';
    $moreStyle = $isMore ? 'color:#fff;font-weight:700;' : '';

    return <<<HTML
$styles
<nav class="navbar">
    <a href="dashboard.php" class="nav-brand">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo"> SGI
    </a>

    <!-- Mobile Top Actions (Only shown <= 768px) -->
    <div class="nav-mobile-actions">
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif(event)" id="bellBtnMobile">
                🔔$badge
            </button>
            <div class="notif-dropdown" id="notifDropMobile">
                <div class="notif-dropdown-header">Notifications <span style="display:flex;gap:10px;"><a href="#" onclick="markAll(event)">Mark read</a><a href="#" onclick="clearAll(event)">Clear all</a></span></div>
                <div class="notif-list-scroll" id="notifListMobile"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <button class="nav-hamburger" onclick="toggleMobileMenu()" aria-label="Toggle navigation">
            <span></span><span></span><span></span>
        </button>
    </div>

    <!-- Links: Horizontal row on Desktop, Slide Drawer on Mobile -->
    <div class="nav-links" id="sgiNavLinks">
        <a href="dashboard.php" style="$homeStyle">🏠 Home</a>

        <div class="nav-dropdown">
            <button type="button" class="nav-dropdown-btn" style="$acadStyle" onclick="toggleMobileDropdown(this)">📚 Academics <span class="caret">▾</span></button>
            <div class="nav-dropdown-menu">
                <a href="academics.php"><span>📊</span> Marks & SGI</a>
                <a href="attendance.php"><span>📅</span> Attendance Tracker</a>
                <a href="attendance_calendar.php"><span>🗓️</span> Attendance Calendar</a>
                <a href="attendance_od_request.php"><span>📝</span> OD Request</a>
            </div>
        </div>

        <a href="leap.php" style="color:#f5a623;font-weight:700;"><img src="/LEAP.png" alt="LEAP" style="width:16px;height:16px;object-fit:contain;border-radius:3px;vertical-align:middle;margin-right:4px;">LEAP</a>
        <a href="print_select.php">🖨️ Print</a>

        <div class="nav-dropdown">
            <button type="button" class="nav-dropdown-btn" style="$moreStyle" onclick="toggleMobileDropdown(this)">ℹ️ More <span class="caret">▾</span></button>
            <div class="nav-dropdown-menu">
                <a href="calendar.php"><span>📅</span> Academic Calendar</a>
                <a href="announcements.php"><span>📢</span> Announcements</a>
                <a href="update_profile.php"><span>👤</span> Edit Profile</a>
                <a href="about.php"><span>📖</span> About SGI</a>
                <a href="contact.php"><span>✉️</span> Contact Support</a>
            </div>
        </div>

        <!-- Desktop Bell (only visible > 768px) -->
        <div class="notif-bell-wrap nav-desktop-bell">
            <button class="notif-bell-btn" onclick="toggleNotif(event)" id="bellBtn">
                🔔$badge
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <span style="display:flex;gap:10px;"><a href="#" onclick="markAll(event)">Mark read</a><a href="#" onclick="clearAll(event)">Clear all</a></span></div>
                <div class="notif-list-scroll" id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>

        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
HTML;
}

function renderMentorNavbar($active = '', $unreadCount = 0) {
    $current = basename($active ?: $_SERVER['PHP_SELF']);
    $badge = $unreadCount > 0 ? "<span class=\"notif-badge\">$unreadCount</span>" : '';
    $styles = renderNavMobileStyles();

    $isHome = ($current === 'mentor_dashboard.php');
    $isApprovals = ($current === 'mentor_approvals.php');
    $isAttendance = in_array($current, ['mentor_attendance.php', 'mentor_attendance_calendar.php', 'mentor_attendance_mark.php', 'mentor_attendance_od.php']);
    $isPortal = in_array($current, ['mentor_calendar.php', 'mentor_announcements.php', 'mentor_timetable.php']);
    $isMore = in_array($current, ['mentor_update_profile.php', 'mentor_about.php', 'mentor_contact.php']);

    $homeStyle = $isHome ? 'color:#fff;font-weight:700;' : '';
    $appStyle = $isApprovals ? 'color:#fff;font-weight:700;' : '';
    $attStyle = $isAttendance ? 'color:#fff;font-weight:700;' : '';
    $portalStyle = $isPortal ? 'color:#fff;font-weight:700;' : '';
    $moreStyle = $isMore ? 'color:#fff;font-weight:700;' : '';

    return <<<HTML
$styles
<nav class="navbar" style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);">
    <a href="mentor_dashboard.php" class="nav-brand">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo"> SGI <span style="font-size:13px;opacity:0.7;font-weight:400;">Mentor</span>
    </a>

    <!-- Mobile Top Actions (Only shown <= 768px) -->
    <div class="nav-mobile-actions">
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif(event)" id="bellBtnMobile">
                🔔$badge
            </button>
            <div class="notif-dropdown" id="notifDropMobile">
                <div class="notif-dropdown-header">Notifications <span style="display:flex;gap:10px;"><a href="#" onclick="markAll(event)">Mark read</a><a href="#" onclick="clearAll(event)">Clear all</a></span></div>
                <div class="notif-list-scroll" id="notifListMobile"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <button class="nav-hamburger" onclick="toggleMobileMenu()" aria-label="Toggle navigation">
            <span></span><span></span><span></span>
        </button>
    </div>

    <!-- Links: Horizontal row on Desktop, Slide Drawer on Mobile -->
    <div class="nav-links" id="sgiNavLinks">
        <a href="mentor_dashboard.php" style="$homeStyle">🏠 Home</a>
        <a href="mentor_approvals.php" style="$appStyle">✅ Approvals</a>
        <a href="mentor_leap_dashboard.php" style="color:#f5a623;font-weight:700;"><img src="/LEAP.png" alt="LEAP" style="width:16px;height:16px;object-fit:contain;border-radius:3px;vertical-align:middle;margin-right:4px;">LEAP</a>

        <div class="nav-dropdown">
            <button type="button" class="nav-dropdown-btn" style="$attStyle" onclick="toggleMobileDropdown(this)">📅 Attendance <span class="caret">▾</span></button>
            <div class="nav-dropdown-menu">
                <a href="mentor_attendance.php"><span>📊</span> Attendance Overview</a>
                <a href="mentor_attendance_mark.php"><span>✍️</span> Mark Attendance</a>
                <a href="mentor_attendance_calendar.php"><span>🗓️</span> Attendance Calendar</a>
                <a href="mentor_attendance_od.php"><span>📝</span> OD Requests</a>
            </div>
        </div>

        <div class="nav-dropdown">
            <button type="button" class="nav-dropdown-btn" style="$portalStyle" onclick="toggleMobileDropdown(this)">📅 Portal <span class="caret">▾</span></button>
            <div class="nav-dropdown-menu">
                <a href="mentor_calendar.php"><span>📅</span> Academic Calendar</a>
                <a href="mentor_announcements.php"><span>📢</span> Announcements</a>
                <a href="mentor_timetable.php"><span>🕒</span> Timetable</a>
            </div>
        </div>

        <div class="nav-dropdown">
            <button type="button" class="nav-dropdown-btn" style="$moreStyle" onclick="toggleMobileDropdown(this)">ℹ️ More <span class="caret">▾</span></button>
            <div class="nav-dropdown-menu">
                <a href="mentor_update_profile.php"><span>👤</span> Profile</a>
                <a href="mentor_about.php"><span>📖</span> About SGI</a>
                <a href="mentor_contact.php"><span>✉️</span> Contact</a>
            </div>
        </div>

        <!-- Desktop Bell (only visible > 768px) -->
        <div class="notif-bell-wrap nav-desktop-bell">
            <button class="notif-bell-btn" onclick="toggleNotif(event)" id="bellBtn">
                🔔$badge
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <span style="display:flex;gap:10px;"><a href="#" onclick="markAll(event)">Mark read</a><a href="#" onclick="clearAll(event)">Clear all</a></span></div>
                <div class="notif-list-scroll" id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>

        <a href="mentor_logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
HTML;
}
