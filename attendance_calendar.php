<?php
include 'config.php';
requireLogin();
$u = $_SESSION['user'];
$unreadCount = $notifications->countDocuments(['roll'=>$u['roll'],'read'=>false]);
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }
$firstDay = mktime(0,0,0,$month,1,$year);
$daysInMonth = (int)date('t', $firstDay);
$startDow = (int)date('w', $firstDay);
$monthName = date('F Y', $firstDay);
$prev = ['month'=>$month-1==0?12:$month-1,'year'=>$month-1==0?$year-1:$year];
$next = ['month'=>$month+1==13?1:$month+1,'year'=>$month+1==13?$year+1:$year];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGI – Attendance Calendar</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .att-hero { background: linear-gradient(135deg, #1a1a2e, #e94560); padding: 40px 24px; border-radius: 20px; margin-top: 24px; text-align: center; color: #fff; }
        .att-hero h1 { margin: 0 0 8px; font-size: 28px; }
        .att-hero p { margin: 0; opacity: 0.9; font-size: 15px; }

        .att-nav-links { display: flex; gap: 12px; margin-top: 20px; justify-content: center; flex-wrap: wrap; }
        .att-nav-btn { padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.2s; border: 2px solid #eee; background: #fff; color: #333; }
        .att-nav-btn:hover { border-color: #e94560; color: #e94560; }
        .att-nav-btn.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }

        .cal-box { background: #fff; border-radius: 16px; padding: 28px; margin-top: 24px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
        .cal-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .cal-nav h2 { color: #1a1a2e; font-size: 20px; }
        .cal-nav-btn { padding: 8px 18px; background: #f0f2f5; border-radius: 10px; text-decoration: none; color: #333; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .cal-nav-btn:hover { background: #1a1a2e; color: #fff; }

        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .cal-day-name { text-align: center; font-size: 12px; font-weight: 600; color: #888; padding: 8px 4px; text-transform: uppercase; }
        .cal-cell { min-height: 80px; border-radius: 10px; border: 2px solid #f0f2f5; padding: 6px; cursor: pointer; transition: all 0.2s; position: relative; }
        .cal-cell.empty { border: none; cursor: default; }
        .cal-cell:hover:not(.empty) { border-color: #e94560; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .cal-cell.today { border-color: #e94560; border-width: 3px; }
        .cal-cell.holiday { background: #f0f0f0; }
        .cal-cell.all-present { background: #d4edda; }
        .cal-cell.all-absent { background: #f8d7da; }
        .cal-cell.mixed { background: #fff3cd; }
        .cal-date { font-size: 14px; font-weight: 700; color: #1a1a2e; }
        .cal-status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin: 1px; }
        .cal-holiday-label { font-size: 9px; color: #888; margin-top: 4px; }

        .cal-legend { display: flex; gap: 16px; margin-top: 20px; flex-wrap: wrap; }
        .cal-legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #555; }
        .cal-legend-dot { width: 12px; height: 12px; border-radius: 50%; }

        .detail-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .detail-modal.active { display: flex; }
        .detail-box { background: #fff; border-radius: 20px; max-width: 700px; width: 90%; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalSlide 0.3s ease; }
        @keyframes modalSlide { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .detail-header { background: linear-gradient(135deg, #1a1a2e, #e94560); padding: 20px 24px; color: #fff; display: flex; justify-content: space-between; align-items: center; border-radius: 20px 20px 0 0; }
        .detail-header h3 { margin: 0; font-size: 18px; }
        .detail-close { background: none; border: none; color: #fff; font-size: 24px; cursor: pointer; }
        .detail-body { padding: 24px; }
        .detail-body table { width: 100%; border-collapse: collapse; }
        .detail-body th { text-align: left; font-size: 11px; color: #888; text-transform: uppercase; padding: 8px 12px; border-bottom: 2px solid #eee; }
        .detail-body td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid #f0f2f5; }
        .att-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .att-badge.PRESENT { background: #d4edda; color: #155724; }
        .att-badge.ABSENT { background: #f8d7da; color: #721c24; }
        .att-badge.OD { background: #d1ecf1; color: #0c5460; }
        .att-badge.LEAVE { background: #fff3cd; color: #856404; }
        .att-badge.SUSPENDED { background: #e2e3e5; color: #383d41; }
        .att-badge.CANCELLED { background: #f8d7da; color: #721c24; }
        .sess-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .sess-badge.CONDUCTED { background: #d4edda; color: #155724; }
        .sess-badge.SCHEDULED { background: #cce5ff; color: #004085; }
        .sess-badge.SUSPENDED { background: #e2e3e5; color: #383d41; }
        .sess-badge.CANCELLED { background: #f8d7da; color: #721c24; }
        .sess-badge.SUBSTITUTION { background: #fff3cd; color: #856404; }
        .sess-badge.RESCHEDULED { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
<nav class="navbar">
<a href="dashboard.php" class="nav-brand">
    <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo"> SGI
</a>
    <div class="nav-links">
        <a href="dashboard.php">Home</a>
        <a href="attendance.php" style="color:#fff;font-weight:600;">Attendance</a>
        <a href="update_profile.php">Profile</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="print_select.php">Print</a>
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
                &#128276;<?php if($unreadCount>0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <span style="display:flex;gap:10px;"><a href="#" onclick="markAll(event)">Mark read</a><a href="#" onclick="clearAll(event)">Clear all</a></span></div>
                <div class="notif-list-scroll" id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
    <div class="att-hero">
        <h1>Attendance Calendar</h1>
        <p>Click any date to view period-wise attendance details</p>
    </div>

    <div class="att-nav-links">
        <a href="attendance.php" class="att-nav-btn">Overview</a>
        <a href="attendance_calendar.php" class="att-nav-btn active">Attendance Calendar</a>
        <a href="attendance_od_request.php" class="att-nav-btn">Apply OD / Leave</a>
    </div>

    <div class="cal-box">
        <div class="cal-nav">
            <a href="attendance_calendar.php?month=<?= $prev['month'] ?>&year=<?= $prev['year'] ?>" class="cal-nav-btn">&#8592; Prev</a>
            <h2><?= $monthName ?></h2>
            <a href="attendance_calendar.php?month=<?= $next['month'] ?>&year=<?= $next['year'] ?>" class="cal-nav-btn">Next &#8594;</a>
        </div>

        <div class="cal-grid">
            <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                <div class="cal-day-name"><?= $d ?></div>
            <?php endforeach; ?>
            <?php for($i=0;$i<$startDow;$i++): ?>
                <div class="cal-cell empty"></div>
            <?php endfor; ?>
            <?php
            $today = (int)date('j'); $todayM = (int)date('n'); $todayY = (int)date('Y');
            for($d=1;$d<=$daysInMonth;$d++):
                $isToday = ($d==$today && $month==$todayM && $year==$todayY);
            ?>
            <div class="cal-cell <?= $isToday?'today':'' ?>" id="cell-<?= $d ?>" onclick="showDayDetail(<?= $d ?>)">
                <div class="cal-date"><?= $d ?></div>
                <div id="dots-<?= $d ?>"></div>
                <div id="holiday-<?= $d ?>"></div>
            </div>
            <?php endfor; ?>
        </div>

        <div class="cal-legend">
            <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#28a745;"></span>Present</div>
            <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#dc3545;"></span>Absent</div>
            <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#17a2b8;"></span>OD</div>
            <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#ffc107;"></span>Leave/Special</div>
            <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#aaa;"></span>Holiday/Suspended</div>
        </div>
    </div>
</div>

<div class="detail-modal" id="dayModal">
    <div class="detail-box">
        <div class="detail-header">
            <h3 id="modalTitle">Date Details</h3>
            <button class="detail-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="detail-body" id="modalBody"></div>
    </div>
</div>

<script>
const monthData = <?= json_encode(['month' => $month, 'year' => $year]) ?>;
let calData = {};

document.addEventListener('DOMContentLoaded', function() {
    loadCalendar();
});

function loadCalendar() {
    fetch(`attendance_api.php?action=student_calendar&month=${monthData.month}&year=${monthData.year}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') return;
            calData = data;
            renderCalendar();
        });
}

function renderCalendar() {
    const days = calData.days || {};
    const holidays = calData.holidays || {};

    Object.keys(holidays).forEach(day => {
        const cell = document.getElementById('cell-' + day);
        if (cell) {
            cell.classList.add('holiday');
            const hDiv = document.getElementById('holiday-' + day);
            if (hDiv) hDiv.innerHTML = '<div class="cal-holiday-label">' + escapeHtml(holidays[day]) + '</div>';
        }
    });

    Object.keys(days).forEach(day => {
        const sessions = days[day];
        const dotsDiv = document.getElementById('dots-' + day);
        const cell = document.getElementById('cell-' + day);
        if (!dotsDiv || !cell) return;

        let allPresent = true, allAbsent = true;
        let dotHtml = '';
        sessions.forEach(s => {
            const att = s.attendance;
            let color = '#aaa';
            if (att === 'PRESENT') { color = '#28a745'; allAbsent = false; }
            else if (att === 'ABSENT') { color = '#dc3545'; allPresent = false; }
            else if (att === 'OD') { color = '#17a2b8'; allAbsent = false; }
            else if (att === 'LEAVE') { color = '#ffc107'; allAbsent = false; }
            else if (s.session_status === 'SUSPENDED' || s.session_status === 'CANCELLED') { color = '#aaa'; allPresent = false; allAbsent = false; }
            dotHtml += `<span class="cal-status-dot" style="background:${color};"></span>`;
        });
        dotsDiv.innerHTML = dotHtml;

        if (sessions.length > 0) {
            if (allPresent) cell.classList.add('all-present');
            else if (allAbsent) cell.classList.add('all-absent');
            else cell.classList.add('mixed');
        }
    });
}

function showDayDetail(day) {
    const sessions = (calData.days || {})[day] || [];
    const holiday = (calData.holidays || {})[day];

    const dateStr = `${day} ${monthData.month} ${monthData.year}`;
    document.getElementById('modalTitle').textContent = dateStr;

    let html = '';
    if (holiday) {
        html += `<div style="background:#f0f0f0;padding:16px;border-radius:12px;margin-bottom:16px;text-align:center;">
            <div style="font-size:24px;">🏖️</div>
            <div style="font-weight:600;color:#555;margin-top:8px;">Holiday: ${escapeHtml(holiday)}</div>
        </div>`;
    }

    if (sessions.length === 0 && !holiday) {
        html += '<div style="text-align:center;padding:40px;color:#888;">No sessions scheduled for this date.</div>';
    } else if (sessions.length > 0) {
        html += `<table>
            <thead>
                <tr>
                    <th>Hour</th>
                    <th>Time</th>
                    <th>Subject</th>
                    <th>Faculty</th>
                    <th>Session</th>
                    <th>Attendance</th>
                </tr>
            </thead>
            <tbody>
                ${sessions.map(s => {
                    const attBadge = s.attendance ? `<span class="att-badge ${s.attendance}">${s.attendance}</span>` : '<span style="color:#aaa;">—</span>';
                    const sessBadge = `<span class="sess-badge ${s.session_status}">${s.session_status}</span>`;
                    return `<tr>
                        <td><strong>H${s.hour}</strong></td>
                        <td>${s.start_time}-${s.end_time}</td>
                        <td>${escapeHtml(s.subject)}</td>
                        <td>${escapeHtml(s.faculty)}</td>
                        <td>${sessBadge}</td>
                        <td>${attBadge}</td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>`;
    }

    document.getElementById('modalBody').innerHTML = html;
    document.getElementById('dayModal').classList.add('active');
}

function closeModal() { document.getElementById('dayModal').classList.remove('active'); }

function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

document.getElementById('dayModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });

function toggleNotif() { const d = document.getElementById('notifDrop'); d.classList.toggle('open'); if (d.classList.contains('open')) loadNotifs(); }
function loadNotifs() { fetch('notifications.php?fetch=1').then(r=>r.json()).then(data=>{ const l=document.getElementById('notifList'); if(!data.length){l.innerHTML='<div class="notif-empty">No notifications</div>';return;} l.innerHTML=data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}</div><div class="notif-time">${n.time}</div></div>`).join(''); }); }
function markAll(e) { e.preventDefault(); fetch('notifications.php?mark_all=1'); document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread')); const b=document.querySelector('.notif-badge'); if(b) b.remove(); }
function clearAll(e) { e.preventDefault(); fetch('notifications.php?delete_all=1'); document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>'; const b=document.querySelector('.notif-badge'); if(b) b.remove(); }
document.addEventListener('click', e => { const btn=document.getElementById('bellBtn'); const d=document.getElementById('notifDrop'); if(btn&&d&&!btn.contains(e.target)&&!d.contains(e.target)) d.classList.remove('open'); });
</script>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>
