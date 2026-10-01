<?php
// leap_auth.php — LEAP shared auth guards, notification helper, nav renderers
// Requires config.php to already be included.

// LEAP collections — already declared in config.php.example; guard prevents re-declaration
if (!isset($leap_applications)) {
    $leap_applications      = $db->leap_applications;
    $leap_memberships       = $db->leap_memberships;
    $leap_announcements     = $db->leap_announcements;
    $leap_activities        = $db->leap_activities;
    $leap_training          = $db->leap_training;
    $leap_training_sessions = $db->leap_training_sessions;
    $leap_attendance        = $db->leap_attendance;
    $leap_tests             = $db->leap_tests;
    $leap_test_results      = $db->leap_test_results;
}

// Require active LEAP membership for student pages
function requireLeapMember() {
    global $leap_memberships;
    requireLogin();
    $u = $_SESSION['user'];
    $mem = $leap_memberships->findOne(['student_id' => $u['roll'], 'status' => 'ACTIVE']);
    if (!$mem) {
        header('Location: leap_apply.php');
        exit;
    }
    return $mem;
}

// Require mentor session for mentor LEAP pages
function requireMentorLeap() {
    if (!isset($_SESSION['mentor'])) {
        header('Location: mentor_login.php');
        exit;
    }
    if (isset($_SESSION['last_mentor_activity']) && (time() - $_SESSION['last_mentor_activity'] > 1800)) {
        unset($_SESSION['mentor']);
        header('Location: mentor_login.php?timeout=1');
        exit;
    }
    $_SESSION['last_mentor_activity'] = time();
    return $_SESSION['mentor'];
}

// Send a notification to a student (by roll)
function leapNotifyStudent($roll, $message, $link = '') {
    global $notifications;
    $notifications->insertOne([
        'roll'       => $roll,
        'message'    => $message . ($link ? " <a href=\"$link\">View</a>" : ''),
        'type'       => 'leap',
        'read'       => false,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
    ]);
}

// Send a notification to a mentor (by mentor_id)
function leapNotifyMentor($mentor_id, $message, $link = '') {
    global $notifications;
    $notifications->insertOne([
        'mentor_id'  => $mentor_id,
        'message'    => $message . ($link ? " <a href=\"$link\">View</a>" : ''),
        'type'       => 'leap',
        'read'       => false,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
    ]);
}

// Render student LEAP navbar (call after $u and $unreadCount are set)
function leapStudentNav($u, $unreadCount, $active = '') {
    $pages = [
        'leap.php'              => 'Home',
        'leap_announcements.php'=> 'Announcements',
        'leap_activities.php'   => 'Activities',
        'leap_training.php'     => 'Training',
        'leap_tests.php'        => 'Tests',
        'leap_progress.php'     => 'My Progress',
        'leap_results.php'      => 'Results',
    ];
    $links = '';
    foreach ($pages as $href => $label) {
        $isCurrent = (basename($active) === $href || basename($_SERVER['PHP_SELF']) === $href);
        $style = $isCurrent ? 'color:#fff;font-weight:700;border-bottom:2px solid #f5a623;padding-bottom:2px;' : '';
        $links .= "<a href=\"$href\" style=\"$style\">$label</a>";
    }
    $badge = $unreadCount > 0 ? "<span class=\"notif-badge\">$unreadCount</span>" : '';
    echo <<<HTML
<nav class="navbar" style="background:linear-gradient(135deg,#1a1a2e,#f5a623);">
    <a href="leap.php" class="nav-brand" style="color:#fff;">
        <img src="/LEAP.png" alt="LEAP" class="nav-logo" style="width:32px;height:32px;object-fit:contain;border-radius:6px;">
        <span style="color:#f5a623;font-weight:800;">LEAP</span><span style="font-size:12px;opacity:0.7;margin-left:4px;">The Placement Series</span>
    </a>
    <div class="nav-links">
        $links
        <a href="dashboard.php" style="opacity:0.7;font-size:12px;">← SGI</a>
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

// Render mentor LEAP navbar
function leapMentorNav($m, $unreadCount) {
    $pages = [
        'mentor_leap_applications.php' => 'Applications',
        'mentor_leap_pacc.php'         => 'PACC',
        'mentor_leap_announcements.php'=> 'Announcements',
        'mentor_leap_activities.php'   => 'Activities',
        'mentor_leap_training.php'     => 'Training',
        'mentor_leap_attendance.php'   => 'Attendance',
        'mentor_leap_tests.php'        => 'Tests',
        'mentor_leap_results.php'      => 'Results',
    ];
    $links = '';
    foreach ($pages as $href => $label) {
        $isCurrent = (basename($_SERVER['PHP_SELF']) === $href);
        $style = $isCurrent ? 'color:#fff;font-weight:700;border-bottom:2px solid #f5a623;padding-bottom:2px;' : '';
        $links .= "<a href=\"$href\" style=\"$style\">$label</a>";
    }
    $badge = $unreadCount > 0 ? "<span class=\"notif-badge\">$unreadCount</span>" : '';
    echo <<<HTML
<nav class="navbar" style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);">
    <a href="mentor_dashboard.php" class="nav-brand" style="color:#fff;">
        <img src="/LEAP.png" alt="LEAP" class="nav-logo" style="width:32px;height:32px;object-fit:contain;border-radius:6px;">
        <span style="color:#f5a623;font-weight:800;">LEAP</span><span style="font-size:12px;opacity:0.7;margin-left:4px;">Mentor Portal</span>
    </a>
    <div class="nav-links">
        $links
        <a href="mentor_dashboard.php" style="opacity:0.7;font-size:12px;">← Dashboard</a>
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

// Standard notification JS (student)
function leapNotifJS() {
    echo <<<JS
<script>
function toggleNotif(){const d=document.getElementById('notifDrop');d.classList.toggle('open');if(d.classList.contains('open'))loadNotifs();}
function loadNotifs(){fetch('notifications.php?fetch=1').then(r=>r.json()).then(data=>{const l=document.getElementById('notifList');if(!data.length){l.innerHTML='<div class="notif-empty">No notifications</div>';return;}l.innerHTML=data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}</div><div class="notif-time">${n.time}</div></div>`).join('');});}
function markAll(e){e.preventDefault();fetch('notifications.php?mark_all=1');document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread'));const b=document.querySelector('.notif-badge');if(b)b.remove();}
function clearAll(e){e.preventDefault();fetch('notifications.php?delete_all=1');document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>';const b=document.querySelector('.notif-badge');if(b)b.remove();}
document.addEventListener('click',e=>{const btn=document.getElementById('bellBtn');const drop=document.getElementById('notifDrop');if(btn&&drop&&!btn.contains(e.target)&&!drop.contains(e.target))drop.classList.remove('open');});
</script>
JS;
}

// Standard notification JS (mentor)
function leapMentorNotifJS() {
    echo <<<JS
<script>
function toggleNotif(){const d=document.getElementById('notifDrop');d.classList.toggle('open');if(d.classList.contains('open'))loadNotifs();}
function loadNotifs(){fetch('notifications.php?fetch=1&mentor=1').then(r=>r.json()).then(data=>{const l=document.getElementById('notifList');if(!data.length){l.innerHTML='<div class="notif-empty">No notifications</div>';return;}l.innerHTML=data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}</div><div class="notif-time">${n.time}</div></div>`).join('');});}
function markAll(e){e.preventDefault();fetch('notifications.php?mark_all=1&mentor=1');document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread'));const b=document.querySelector('.notif-badge');if(b)b.remove();}
function clearAll(e){e.preventDefault();fetch('notifications.php?delete_all=1&mentor=1');document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>';const b=document.querySelector('.notif-badge');if(b)b.remove();}
document.addEventListener('click',e=>{const btn=document.getElementById('bellBtn');const drop=document.getElementById('notifDrop');if(btn&&drop&&!btn.contains(e.target)&&!drop.contains(e.target))drop.classList.remove('open');});
</script>
JS;
}

function leapFooter() {
    echo '<div class="copyright-footer">&copy; ' . date('Y') . ' Student Growth Index (SGI) · LEAP – The Placement Series. All rights reserved by TG.</div>';
}
