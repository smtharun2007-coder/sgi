<?php
include 'config.php';
if (!isset($_SESSION['mentor'])) { header("Location: mentor_login.php"); exit; }
$m = $_SESSION['mentor'];
$unreadCount = $notifications->countDocuments(['mentor_id'=>$m['mentor_id'],'read'=>false]);

// Fetch distinct batches from students
$studentCursor = $users->find(['mentor_id' => $m['mentor_id']]);
$batches = [];
foreach ($studentCursor as $s) {
    $b = $s['batch_no'] ?? '';
    if ($b && !in_array($b, $batches)) $batches[] = $b;
}
sort($batches);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGI – Attendance Management</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .att-hero { background: linear-gradient(135deg, #1a1a2e, #8e44ad); padding: 40px 24px; border-radius: 20px; margin-top: 24px; text-align: center; color: #fff; }
        .att-hero h1 { margin: 0 0 8px; font-size: 28px; }
        .att-hero p { margin: 0; opacity: 0.9; font-size: 15px; }

        .att-nav-links { display: flex; gap: 12px; margin-top: 20px; justify-content: center; flex-wrap: wrap; }
        .att-nav-btn { padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.2s; border: 2px solid #eee; background: #fff; color: #333; }
        .att-nav-btn:hover { border-color: #8e44ad; color: #8e44ad; }
        .att-nav-btn.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }

        .att-cards { display: flex; gap: 20px; margin-top: 24px; flex-wrap: wrap; }
        .att-card { flex: 1; min-width: 200px; background: #fff; border-radius: 16px; padding: 28px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); text-align: center; transition: all 0.3s; text-decoration: none; color: inherit; }
        .att-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .att-card .att-icon { font-size: 40px; margin-bottom: 12px; }
        .att-card h3 { color: #1a1a2e; font-size: 16px; margin: 0 0 6px; }
        .att-card p { color: #888; font-size: 13px; margin: 0; }

        .batch-selector { background: #fff; border-radius: 16px; padding: 24px; margin-top: 24px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
        .batch-selector h3 { color: #1a1a2e; font-size: 18px; margin-bottom: 16px; }
        .batch-selector select { max-width: 300px; }
        .batch-selector label { margin-bottom: 8px; }

        .report-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .report-table th { text-align: left; font-size: 11px; color: #888; text-transform: uppercase; padding: 10px 12px; border-bottom: 2px solid #eee; }
        .report-table td { padding: 12px; font-size: 14px; border-bottom: 1px solid #f0f2f5; }
        .report-table tr:last-child td { border-bottom: none; }
        .pct-badge { padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; }
        .pct-badge.good { background: #d4edda; color: #155724; }
        .pct-badge.warn { background: #fff3cd; color: #856404; }
        .pct-badge.bad { background: #f8d7da; color: #721c24; }

        .empty-state { text-align: center; padding: 40px; color: #888; }
    </style>
</head>
<body>
<nav class="navbar" style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);">
    <a href="mentor_dashboard.php" class="nav-brand">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo"> SGI <span style="font-size:13px;opacity:0.7;font-weight:400;">Mentor</span>
    </a>
    <div class="nav-links">
        <a href="mentor_dashboard.php">Home</a>
        <a href="mentor_attendance.php" style="color:#fff;font-weight:700;">Attendance</a>
        <a href="mentor_approvals.php">Approvals</a>
        <a href="mentor_calendar.php">Calendar</a>
        <a href="mentor_announcements.php">Announcements</a>
        <a href="mentor_update_profile.php">Profile</a>
        <a href="mentor_about.php">About</a>
        <a href="mentor_contact.php">Contact</a>
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
                🔔<?php if($unreadCount>0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <span style="display:flex;gap:10px;"><a href="#" onclick="markAll(event)">Mark read</a><a href="#" onclick="clearAll(event)">Clear all</a></span></div>
                <div class="notif-list-scroll" id="notifList"><div class="notif-empty">Loading…</div></div>
            </div>
        </div>
        <a href="mentor_logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
    <div class="att-hero">
        <h1>Attendance Management</h1>
        <p>Manage timetables, mark attendance, declare holidays, and review OD/Leave requests</p>
    </div>

    <div class="att-nav-links">
        <a href="mentor_attendance.php" class="att-nav-btn active">Dashboard</a>
        <a href="mentor_timetable.php" class="att-nav-btn">Timetable</a>
        <a href="mentor_attendance_calendar.php" class="att-nav-btn">Calendar & Mark</a>
        <a href="mentor_attendance_od.php" class="att-nav-btn">OD / Leave Review</a>
    </div>

    <div class="att-cards">
        <a href="mentor_timetable.php" class="att-card">
            <div class="att-icon">📅</div>
            <h3>Timetable</h3>
            <p>Set up weekly class schedules per batch & semester</p>
        </a>
        <a href="mentor_attendance_calendar.php" class="att-card">
            <div class="att-icon">✅</div>
            <h3>Mark Attendance</h3>
            <p>Generate sessions and mark attendance for any date</p>
        </a>
        <a href="mentor_attendance_od.php" class="att-card">
            <div class="att-icon">📋</div>
            <h3>OD / Leave Review</h3>
            <p>Approve or reject student OD/Leave requests</p>
        </a>
    </div>

    <div class="batch-selector">
        <h3>Batch Attendance Report</h3>
        <label>Select Batch & Semester</label>
        <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <div style="flex:1;min-width:150px;">
                <select id="reportBatch" onchange="loadReport()">
                    <option value="">Select batch...</option>
                    <?php foreach($batches as $b): ?>
                    <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1;min-width:150px;">
                <select id="reportSemester" onchange="loadReport()">
                    <option value="">Select semester...</option>
                    <?php for($s=1;$s<=8;$s++): ?>
                    <option value="<?= $s ?>">Semester <?= $s ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div id="reportArea" style="margin-top:16px;">
            <div class="empty-state">Select a batch and semester to view the attendance report.</div>
        </div>
    </div>
</div>
<script>
function showToast(message, type='info') {
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;top:80px;right:20px;background:${type==='success'?'#28a745':type==='error'?'#dc3545':'#17a2b8'};color:#fff;padding:14px 24px;border-radius:12px;font-size:14px;font-weight:600;z-index:10000;box-shadow:0 8px 30px rgba(0,0,0,0.2);animation:toastSlideIn 0.3s ease;max-width:350px;`;
    toast.textContent = message;
    document.body.appendChild(toast);
    if (!document.getElementById('toastStyles')) {
        const s = document.createElement('style');
        s.id = 'toastStyles';
        s.textContent = '@keyframes toastSlideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}@keyframes toastSlideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(100%);opacity:0}}';
        document.head.appendChild(s);
    }
    setTimeout(() => { toast.style.animation = 'toastSlideOut 0.3s ease'; setTimeout(() => toast.remove(), 300); }, 3000);
}

function loadReport() {
    const batch = document.getElementById('reportBatch').value;
    const semester = document.getElementById('reportSemester').value;
    const area = document.getElementById('reportArea');

    if (!batch || !semester) {
        area.innerHTML = '<div class="empty-state">Select a batch and semester to view the attendance report.</div>';
        return;
    }

    area.innerHTML = '<div class="empty-state">Loading...</div>';

    fetch(`attendance_api.php?action=batch_report&batch=${encodeURIComponent(batch)}&semester=${semester}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                area.innerHTML = '<div class="empty-state">Failed to load report.</div>';
                return;
            }
            if (!data.report.length) {
                area.innerHTML = '<div class="empty-state">No students found for this batch.</div>';
                return;
            }
            let html = `<table class="report-table">
                <thead><tr><th>Roll No</th><th>Name</th><th>Attended</th><th>Total</th><th>Percentage</th></tr></thead>
                <tbody>`;
            data.report.forEach(s => {
                const cls = s.percentage >= 80 ? 'good' : s.percentage >= 60 ? 'warn' : 'bad';
                html += `<tr>
                    <td><strong>${escapeHtml(s.roll)}</strong></td>
                    <td>${escapeHtml(s.name)}</td>
                    <td>${s.attended}</td>
                    <td>${s.total}</td>
                    <td><span class="pct-badge ${cls}">${s.percentage}%</span></td>
                </tr>`;
            });
            html += '</tbody></table>';
            area.innerHTML = html;
        });
}

function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

function toggleNotif() { const d = document.getElementById('notifDrop'); d.classList.toggle('open'); if (d.classList.contains('open')) loadNotifs(); }
function loadNotifs() { fetch('notifications.php?fetch=1&mentor=1').then(r=>r.json()).then(data=>{ const l=document.getElementById('notifList'); if(!data.length){l.innerHTML='<div class="notif-empty">No notifications</div>';return;} l.innerHTML=data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}</div><div class="notif-time">${n.time}</div></div>`).join(''); }); }
function markAll(e) { e.preventDefault(); fetch('notifications.php?mark_all=1&mentor=1'); document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread')); const b=document.querySelector('.notif-badge'); if(b) b.remove(); }
function clearAll(e) { e.preventDefault(); fetch('notifications.php?delete_all=1&mentor=1'); document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>'; const b=document.querySelector('.notif-badge'); if(b) b.remove(); }
document.addEventListener('click', e => { const btn=document.getElementById('bellBtn'); const d=document.getElementById('notifDrop'); if(btn&&d&&!btn.contains(e.target)&&!d.contains(e.target)) d.classList.remove('open'); });
</script>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>
