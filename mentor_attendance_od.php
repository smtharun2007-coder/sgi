<?php
include 'config.php';
if (!isset($_SESSION['mentor'])) { header("Location: mentor_login.php"); exit; }
$m = $_SESSION['mentor'];
$unreadCount = $notifications->countDocuments(['mentor_id'=>$m['mentor_id'],'read'=>false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGI – OD / Leave Review</title>
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

        .filter-row { display: flex; gap: 12px; margin-top: 24px; justify-content: center; flex-wrap: wrap; }
        .filter-btn { padding: 8px 18px; border-radius: 10px; font-size: 13px; font-weight: 600; border: 2px solid #eee; background: #fff; color: #333; cursor: pointer; transition: all 0.2s; }
        .filter-btn.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }

        .od-card { background: #fff; border-radius: 16px; padding: 24px; margin-top: 16px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); border-left: 4px solid transparent; }
        .od-card.pending { border-left-color: #ffc107; }
        .od-card.approved { border-left-color: #28a745; }
        .od-card.rejected { border-left-color: #dc3545; }

        .od-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .od-student-name { font-size: 16px; font-weight: 700; color: #1a1a2e; }
        .od-student-roll { font-size: 13px; color: #888; }
        .od-badge { padding: 4px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .od-badge.pending { background: #fff3cd; color: #856404; }
        .od-badge.approved { background: #d4edda; color: #155724; }
        .od-badge.rejected { background: #f8d7da; color: #721c24; }

        .od-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin: 12px 0; }
        .od-detail-item { background: #f8f9fa; padding: 12px 16px; border-radius: 10px; }
        .od-detail-item label { font-size: 11px; color: #888; text-transform: uppercase; display: block; margin-bottom: 4px; }
        .od-detail-item span { font-size: 14px; color: #333; font-weight: 600; }

        .od-reason { background: #f8f9fa; padding: 12px 16px; border-radius: 10px; margin: 12px 0; font-size: 14px; color: #555; }

        .od-actions { display: flex; gap: 12px; margin-top: 16px; align-items: center; }
        .od-remarks { flex: 1; }
        .od-remarks input { padding: 10px 14px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; background: #f8f9fa; width: 100%; box-sizing: border-box; }
        .od-remarks input:focus { border-color: #8e44ad; background: #fff; outline: none; }
        .od-btn { padding: 10px 24px; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; }
        .od-btn.approve { background: #28a745; color: #fff; }
        .od-btn.approve:hover { background: #218838; }
        .od-btn.reject { background: #dc3545; color: #fff; }
        .od-btn.reject:hover { background: #c82333; }

        .empty-state { text-align: center; padding: 60px; color: #888; }
        .empty-state .empty-icon { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }
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
        <h1>OD / Leave Review</h1>
        <p>Approve or reject student On-Duty and Leave requests</p>
    </div>

    <div class="att-nav-links">
        <a href="mentor_attendance.php" class="att-nav-btn">Dashboard</a>
        <a href="mentor_timetable.php" class="att-nav-btn">Timetable</a>
        <a href="mentor_attendance_calendar.php" class="att-nav-btn">Calendar & Mark</a>
        <a href="mentor_attendance_od.php" class="att-nav-btn active">OD / Leave Review</a>
    </div>

    <div class="filter-row">
        <button class="filter-btn active" data-filter="all" onclick="setFilter('all')">All</button>
        <button class="filter-btn" data-filter="pending" onclick="setFilter('pending')">Pending</button>
        <button class="filter-btn" data-filter="approved" onclick="setFilter('approved')">Approved</button>
        <button class="filter-btn" data-filter="rejected" onclick="setFilter('rejected')">Rejected</button>
    </div>

    <div id="odListArea">
        <div class="empty-state"><div class="empty-icon">📋</div><p>Loading...</p></div>
    </div>
</div>
<script>
let currentFilter = 'all';

function setFilter(filter) {
    currentFilter = filter;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.toggle('active', b.dataset.filter === filter));
    loadODList();
}

document.addEventListener('DOMContentLoaded', loadODList);

function loadODList() {
    const area = document.getElementById('odListArea');
    area.innerHTML = '<div class="empty-state"><div class="empty-icon">📋</div><p>Loading...</p></div>';
    fetch(`attendance_api.php?action=od_requests&filter=${currentFilter}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success' || !data.requests.length) {
                area.innerHTML = '<div class="empty-state"><div class="empty-icon">📋</div><p>No requests found.</p></div>';
                return;
            }
            area.innerHTML = data.requests.map(r => {
                const hoursStr = r.hours && r.hours.length ? 'Hours: ' + r.hours.join(', ') : '';
                const dateRange = r.date_from ? (r.date_to && r.date_to !== r.date_from ? `${r.date_from} - ${r.date_to}` : r.date_from) : '';
                const showActions = r.status === 'pending';
                return `<div class="od-card ${r.status}">
                    <div class="od-card-header">
                        <div>
                            <div class="od-student-name">${escapeHtml(r.student_name)}</div>
                            <div class="od-student-roll">${escapeHtml(r.student_roll)} · ${r.created_at}</div>
                        </div>
                        <span class="od-badge ${r.status}">${r.status}</span>
                    </div>
                    <div class="od-details">
                        <div class="od-detail-item"><label>Type</label><span>${escapeHtml(r.od_type)}</span></div>
                        <div class="od-detail-item"><label>Duration</label><span>${escapeHtml(r.duration)}</span></div>
                        ${dateRange ? `<div class="od-detail-item"><label>Date(s)</label><span>${dateRange}</span></div>` : ''}
                        ${hoursStr ? `<div class="od-detail-item"><label>Hours</label><span>${hoursStr}</span></div>` : ''}
                    </div>
                    <div class="od-reason"><strong>Reason:</strong> ${escapeHtml(r.reason)}</div>
                    ${r.mentor_remarks ? `<div style="font-size:13px;color:#888;font-style:italic;margin-top:8px;">Mentor remarks: ${escapeHtml(r.mentor_remarks)}</div>` : ''}
                    ${showActions ? `<div class="od-actions">
                        <div class="od-remarks"><input type="text" id="remarks_${r._id}" placeholder="Remarks (optional)"></div>
                        <button class="od-btn approve" onclick="processOD('${r._id}', 'approved')">Approve</button>
                        <button class="od-btn reject" onclick="processOD('${r._id}', 'rejected')">Reject</button>
                    </div>` : ''}
                </div>`;
            }).join('');
        });
}

function processOD(id, decision) {
    const remarks = document.getElementById('remarks_' + id)?.value || '';
    const formData = new FormData();
    formData.append('action', 'process_od');
    formData.append('od_id', id);
    formData.append('decision', decision);
    formData.append('remarks', remarks);
    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') { showToast(data.message, 'success'); loadODList(); }
            else showToast(data.message || 'Failed', 'error');
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
