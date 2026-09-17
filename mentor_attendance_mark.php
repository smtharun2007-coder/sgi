<?php
include 'config.php';
if (!isset($_SESSION['mentor'])) { header("Location: mentor_login.php"); exit; }
$m = $_SESSION['mentor'];
$unreadCount = $notifications->countDocuments(['mentor_id'=>$m['mentor_id'],'read'=>false]);
$sessionId = $_GET['session_id'] ?? '';
if (!$sessionId) { header("Location: mentor_attendance_calendar.php"); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGI – Mark Attendance</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .mark-header { background: #fff; border-radius: 16px; padding: 28px; margin-top: 24px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
        .mark-header h2 { color: #1a1a2e; font-size: 22px; margin-bottom: 8px; }
        .mark-header .session-meta { color: #888; font-size: 14px; }

        .mark-actions { display: flex; gap: 12px; margin-top: 16px; flex-wrap: wrap; }
        .mark-btn { padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; }
        .mark-btn.all-present { background: #28a745; color: #fff; }
        .mark-btn.all-present:hover { background: #218838; }
        .mark-btn.save { background: linear-gradient(135deg, #1a1a2e, #8e44ad); color: #fff; }
        .mark-btn.back { background: #eee; color: #555; text-decoration: none; }

        .student-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
        .student-table th { text-align: left; font-size: 11px; color: #888; text-transform: uppercase; padding: 12px 16px; border-bottom: 2px solid #eee; background: #f8f9fa; }
        .student-table td { padding: 12px 16px; font-size: 14px; border-bottom: 1px solid #f0f2f5; }
        .student-table tr:last-child td { border-bottom: none; }

        .status-btns { display: flex; gap: 6px; }
        .status-btn { padding: 6px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; border: 2px solid transparent; cursor: pointer; transition: all 0.2s; background: #f0f2f5; color: #888; }
        .status-btn.active.present { background: #28a745; color: #fff; border-color: #28a745; }
        .status-btn.active.absent { background: #dc3545; color: #fff; border-color: #dc3545; }
        .status-btn.active.od { background: #17a2b8; color: #fff; border-color: #17a2b8; }
        .status-btn.active.leave { background: #ffc107; color: #333; border-color: #ffc107; }

        .od-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: #d1ecf1; color: #0c5460; }
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
    <div class="mark-header">
        <h2 id="sessionTitle">Loading...</h2>
        <div class="session-meta" id="sessionMeta"></div>
        <div class="mark-actions">
            <a href="mentor_attendance_calendar.php" class="mark-btn back">&#8592; Back to Calendar</a>
            <button class="mark-btn all-present" onclick="markAllPresent()">Mark All Present</button>
            <button class="mark-btn save" onclick="saveAttendance()">Save Attendance</button>
        </div>
    </div>

    <table class="student-table">
        <thead>
            <tr>
                <th>Roll No</th>
                <th>Name</th>
                <th>Status</th>
                <th>OD Approved</th>
            </tr>
        </thead>
        <tbody id="studentBody">
            <tr><td colspan="4" style="text-align:center;color:#888;">Loading students...</td></tr>
        </tbody>
    </table>
</div>
<script>
const sessionId = '<?= htmlspecialchars($sessionId) ?>';
let students = [];

document.addEventListener('DOMContentLoaded', loadSession);

function loadSession() {
    fetch(`attendance_api.php?action=session_attendance&session_id=${sessionId}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') { showToast(data.message || 'Failed', 'error'); return; }
            const s = data.session;
            document.getElementById('sessionTitle').textContent = `${s.subject} - H${s.hour}`;
            document.getElementById('sessionMeta').textContent = `${s.date} · Batch: ${s.batch} · Semester: ${s.semester} · Status: ${s.status}`;

            students = data.students;
            const body = document.getElementById('studentBody');
            if (!students.length) {
                body.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#888;">No students found.</td></tr>';
                return;
            }
            body.innerHTML = students.map((s, i) => {
                const currentStatus = s.effective_status || s.status || '';
                const odApproved = (s.effective_status === 'OD' && s.original_status !== 'OD');
                return `<tr>
                    <td><strong>${escapeHtml(s.roll)}</strong></td>
                    <td>${escapeHtml(s.name)}</td>
                    <td>
                        <div class="status-btns" data-index="${i}">
                            <button class="status-btn ${currentStatus==='PRESENT'?'active present':''}" data-status="PRESENT" onclick="setStatus(${i},'PRESENT')">Present</button>
                            <button class="status-btn ${currentStatus==='ABSENT'?'active absent':''}" data-status="ABSENT" onclick="setStatus(${i},'ABSENT')">Absent</button>
                            <button class="status-btn ${currentStatus==='OD'?'active od':''}" data-status="OD" onclick="setStatus(${i},'OD')">OD</button>
                            <button class="status-btn ${currentStatus==='LEAVE'?'active leave':''}" data-status="LEAVE" onclick="setStatus(${i},'LEAVE')">Leave</button>
                        </div>
                    </td>
                    <td>${odApproved ? '<span class="od-badge">OD Approved</span>' : '—'}</td>
                </tr>`;
            }).join('');
        });
}

function setStatus(index, status) {
    students[index].status = status;
    const btns = document.querySelectorAll(`.status-btns[data-index="${index}"] .status-btn`);
    btns.forEach(btn => {
        btn.classList.remove('active', 'present', 'absent', 'od', 'leave');
        if (btn.dataset.status === status) {
            btn.classList.add('active', status.toLowerCase());
        }
    });
}

function markAllPresent() {
    students.forEach((s, i) => {
        if (s.effective_status === 'OD' && s.original_status !== 'OD') return;
        s.status = 'PRESENT';
        const btns = document.querySelectorAll(`.status-btns[data-index="${i}"] .status-btn`);
        btns.forEach(btn => {
            btn.classList.remove('active', 'present', 'absent', 'od', 'leave');
            if (btn.dataset.status === 'PRESENT') btn.classList.add('active', 'present');
        });
    });
    showToast('All students marked present (OD-approved kept as OD)', 'success');
}

function saveAttendance() {
    const statuses = students.map(s => ({ roll: s.roll, status: s.status || 'ABSENT' }));
    const formData = new FormData();
    formData.append('action', 'mark_attendance');
    formData.append('session_id', sessionId);
    formData.append('statuses', JSON.stringify(statuses));
    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                setTimeout(() => window.location.href = 'mentor_attendance_calendar.php', 1500);
            } else {
                showToast(data.message || 'Failed', 'error');
            }
        });
}

function showToast(message, type='info') {
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;top:80px;right:20px;background:${type==='success'?'#28a745':type==='error'?'#dc3545':'#17a2b8'};color:#fff;padding:14px 24px;border-radius:12px;font-size:14px;font-weight:600;z-index:10000;box-shadow:0 8px 30px rgba(0,0,0,0.2);animation:toastSlideIn 0.3s ease;max-width:350px;`;
    toast.textContent = message;
    document.body.appendChild(toast);
    if (!document.getElementById('toastStyles')) { const s=document.createElement('style'); s.id='toastStyles'; s.textContent='@keyframes toastSlideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}@keyframes toastSlideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(100%);opacity:0}}'; document.head.appendChild(s); }
    setTimeout(() => { toast.style.animation='toastSlideOut 0.3s ease'; setTimeout(()=>toast.remove(),300); }, 3000);
}

function escapeHtml(text) { const div=document.createElement('div'); div.textContent=text; return div.innerHTML; }

function toggleNotif() { const d=document.getElementById('notifDrop'); d.classList.toggle('open'); if(d.classList.contains('open')) loadNotifs(); }
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
