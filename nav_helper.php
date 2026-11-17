<?php
// nav_helper.php — centralized modern responsive navbar components for SGI

function renderStudentNavbar($active = '', $unreadCount = 0) {
    $current = basename($active ?: $_SERVER['PHP_SELF']);
    $badge = $unreadCount > 0 ? "<span class=\"notif-badge\">$unreadCount</span>" : '';

    $isHome = ($current === 'dashboard.php');
    $isAcademics = in_array($current, ['academics.php', 'add_semester.php', 'delete_semester.php', 'edit_subjects.php', 'credit_subjects.php', 'cat1_marks.php', 'cat2_marks.php', 'cat3_marks.php', 'final_ca_marks.php', 'calculate_sgi.php', 'verify_marks.php', 'semester_detail.php']);
    $isAttendance = in_array($current, ['attendance.php', 'attendance_calendar.php', 'attendance_od_request.php']);
    $isMore = in_array($current, ['update_profile.php', 'about.php', 'contact.php', 'calendar.php', 'announcements.php']);

    $homeStyle = $isHome ? 'color:#fff;font-weight:700;' : '';
    $acadStyle = ($isAcademics || $isAttendance) ? 'color:#fff;font-weight:700;' : '';
    $moreStyle = $isMore ? 'color:#fff;font-weight:700;' : '';

    return <<<HTML
<nav class="navbar">
    <a href="dashboard.php" class="nav-brand">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo"> SGI
    </a>
    <div class="nav-links">
        <a href="dashboard.php" style="$homeStyle">🏠 Home</a>

        <div class="nav-dropdown">
            <button class="nav-dropdown-btn" style="$acadStyle">📚 Academics <span class="caret">▾</span></button>
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
            <button class="nav-dropdown-btn" style="$moreStyle">ℹ️ More <span class="caret">▾</span></button>
            <div class="nav-dropdown-menu">
                <a href="calendar.php"><span>📅</span> Academic Calendar</a>
                <a href="announcements.php"><span>📢</span> Announcements</a>
                <a href="update_profile.php"><span>👤</span> Edit Profile</a>
                <a href="about.php"><span>📖</span> About SGI</a>
                <a href="contact.php"><span>✉️</span> Contact Support</a>
            </div>
        </div>

        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
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
<nav class="navbar" style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);">
    <a href="mentor_dashboard.php" class="nav-brand">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo"> SGI <span style="font-size:13px;opacity:0.7;font-weight:400;">Mentor</span>
    </a>
    <div class="nav-links">
        <a href="mentor_dashboard.php" style="$homeStyle">🏠 Home</a>
        <a href="mentor_approvals.php" style="$appStyle">✅ Approvals</a>
        <a href="mentor_leap_dashboard.php" style="color:#f5a623;font-weight:700;"><img src="/LEAP.png" alt="LEAP" style="width:16px;height:16px;object-fit:contain;border-radius:3px;vertical-align:middle;margin-right:4px;">LEAP</a>

        <div class="nav-dropdown">
            <button class="nav-dropdown-btn" style="$attStyle">📅 Attendance <span class="caret">▾</span></button>
            <div class="nav-dropdown-menu">
                <a href="mentor_attendance.php"><span>📊</span> Attendance Overview</a>
                <a href="mentor_attendance_mark.php"><span>✍️</span> Mark Attendance</a>
                <a href="mentor_attendance_calendar.php"><span>🗓️</span> Attendance Calendar</a>
                <a href="mentor_attendance_od.php"><span>📝</span> OD Requests</a>
            </div>
        </div>

        <div class="nav-dropdown">
            <button class="nav-dropdown-btn" style="$portalStyle">📅 Portal <span class="caret">▾</span></button>
            <div class="nav-dropdown-menu">
                <a href="mentor_calendar.php"><span>📅</span> Academic Calendar</a>
                <a href="mentor_announcements.php"><span>📢</span> Announcements</a>
                <a href="mentor_timetable.php"><span>🕒</span> Timetable</a>
            </div>
        </div>

        <div class="nav-dropdown">
            <button class="nav-dropdown-btn" style="$moreStyle">ℹ️ More <span class="caret">▾</span></button>
            <div class="nav-dropdown-menu">
                <a href="mentor_update_profile.php"><span>👤</span> Profile</a>
                <a href="mentor_about.php"><span>📖</span> About SGI</a>
                <a href="mentor_contact.php"><span>✉️</span> Contact</a>
            </div>
        </div>

        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
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
