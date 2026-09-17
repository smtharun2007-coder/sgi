<?php
include 'config.php';
requireLogin();
$u = $_SESSION['user'];
$unreadCount = $notifications->countDocuments(['roll'=>$u['roll'],'read'=>false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGI – Attendance</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .att-hero {
            background: linear-gradient(135deg, #1a1a2e, #e94560);
            padding: 40px 24px;
            border-radius: 20px;
            margin-top: 24px;
            text-align: center;
            color: #fff;
        }
        .att-hero h1 { margin: 0 0 8px; font-size: 28px; }
        .att-hero p { margin: 0; opacity: 0.9; font-size: 15px; }

        .att-overview-row { display: flex; gap: 16px; margin-top: 24px; flex-wrap: wrap; }
        .att-overview-card {
            flex: 1; min-width: 140px;
            background: #fff; border-radius: 16px; padding: 24px;
            text-align: center; box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            border-top: 4px solid transparent;
            transition: all 0.3s ease;
        }
        .att-overview-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .att-overview-card.overall { border-top-color: #1a1a2e; }
        .att-overview-card.present { border-top-color: #28a745; }
        .att-overview-card.absent { border-top-color: #dc3545; }
        .att-overview-card.od { border-top-color: #17a2b8; }
        .att-overview-card.leave { border-top-color: #ffc107; }
        .att-overview-card .att-num { font-size: 36px; font-weight: 700; color: #1a1a2e; }
        .att-overview-card .att-label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-top: 6px; }

        .att-section {
            background: #fff; border-radius: 16px; padding: 28px;
            margin-top: 24px; box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }
        .att-section h3 { color: #1a1a2e; font-size: 18px; margin-bottom: 16px; }

        .subj-table { width: 100%; border-collapse: collapse; }
        .subj-table th { text-align: left; font-size: 11px; color: #888; text-transform: uppercase; padding: 10px 12px; border-bottom: 2px solid #eee; }
        .subj-table td { padding: 12px; font-size: 14px; border-bottom: 1px solid #f0f2f5; }
        .subj-table tr:last-child td { border-bottom: none; }

        .att-pct-bar { width: 100%; height: 8px; background: #f0f2f5; border-radius: 4px; overflow: hidden; margin-top: 4px; }
        .att-pct-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }

        .od-list { margin-top: 12px; }
        .od-item {
            background: #f8f9fa; border-radius: 12px; padding: 16px 20px;
            margin-bottom: 12px; display: flex; align-items: center; gap: 16px;
            border-left: 4px solid transparent;
        }
        .od-item.pending { border-left-color: #ffc107; }
        .od-item.approved { border-left-color: #28a745; }
        .od-item.rejected { border-left-color: #dc3545; }
        .od-type { font-size: 14px; font-weight: 600; color: #1a1a2e; }
        .od-meta { font-size: 12px; color: #888; margin-top: 4px; }
        .od-badge {
            padding: 4px 12px; border-radius: 20px; font-size: 11px;
            font-weight: 600; text-transform: uppercase; flex-shrink: 0;
        }
        .od-badge.pending { background: #fff3cd; color: #856404; }
        .od-badge.approved { background: #d4edda; color: #155724; }
        .od-badge.rejected { background: #f8d7da; color: #721c24; }

        .btn-od {
            display: inline-block; padding: 10px 24px;
            background: linear-gradient(135deg, #1a1a2e, #e94560);
            color: #fff; border-radius: 10px; font-size: 14px; font-weight: 600;
            text-decoration: none; margin-top: 16px; transition: all 0.3s;
        }
        .btn-od:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(233,69,96,0.3); }

        .empty-state { text-align: center; padding: 40px; color: #888; }
        .empty-state .empty-icon { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }

        .att-nav-links { display: flex; gap: 12px; margin-top: 20px; justify-content: center; flex-wrap: wrap; }
        .att-nav-btn {
            padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600;
            text-decoration: none; transition: all 0.2s; border: 2px solid #eee; background: #fff; color: #333;
        }
        .att-nav-btn:hover { border-color: #e94560; color: #e94560; }
        .att-nav-btn.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }
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
        <h1>Attendance</h1>
        <p>View your overall, subject-wise, and day-wise attendance</p>
    </div>

    <div class="att-nav-links">
        <a href="attendance.php" class="att-nav-btn active">Overview</a>
        <a href="attendance_calendar.php" class="att-nav-btn">Attendance Calendar</a>
        <a href="attendance_od_request.php" class="att-nav-btn">Apply OD / Leave</a>
    </div>

    <div class="att-overview-row" id="overviewCards">
        <div class="att-overview-card overall">
            <div class="att-num" id="overallPct">—</div>
            <div class="att-label">Overall %</div>
        </div>
        <div class="att-overview-card present">
            <div class="att-num" id="presentCount">—</div>
            <div class="att-label">Present</div>
        </div>
        <div class="att-overview-card absent">
            <div class="att-num" id="absentCount">—</div>
            <div class="att-label">Absent</div>
        </div>
        <div class="att-overview-card od">
            <div class="att-num" id="odCount">—</div>
            <div class="att-label">OD</div>
        </div>
        <div class="att-overview-card leave">
            <div class="att-num" id="leaveCount">—</div>
            <div class="att-label">Leave</div>
        </div>
    </div>

    <div class="att-section">
        <h3>Subject-wise Attendance</h3>
        <table class="subj-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Code</th>
                    <th>Attended</th>
                    <th>Total</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody id="subjBody">
                <tr><td colspan="5" style="text-align:center;color:#888;">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="att-section">
        <h3>OD / Leave History</h3>
        <a href="attendance_od_request.php" class="btn-od">+ Apply for OD / Leave</a>
        <div class="od-list" id="odList" style="margin-top:16px;">
            <div class="empty-state"><div class="empty-icon">📋</div><p>Loading...</p></div>
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

document.addEventListener('DOMContentLoaded', function() {
    loadOverview();
    loadODList();
});

function loadOverview() {
    fetch('attendance_api.php?action=student_overview')
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') return;
            document.getElementById('overallPct').textContent = data.overall + '%';
            document.getElementById('presentCount').textContent = data.present;
            document.getElementById('absentCount').textContent = data.absent;
            document.getElementById('odCount').textContent = data.od;
            document.getElementById('leaveCount').textContent = data.leave;

            const body = document.getElementById('subjBody');
            if (!data.subjects.length) {
                body.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888;">No attendance data yet.</td></tr>';
                return;
            }
            body.innerHTML = data.subjects.map(s => {
                const pct = s.total > 0 ? Math.round((s.attended / s.total) * 100, 2) : 0;
                const color = pct >= 80 ? '#28a745' : pct >= 60 ? '#f5a623' : '#dc3545';
                return `<tr>
                    <td><strong>${escapeHtml(s.subject)}</strong></td>
                    <td style="color:#888;">${escapeHtml(s.code)}</td>
                    <td>${s.attended}</td>
                    <td>${s.total}</td>
                    <td>
                        <span style="font-weight:600;color:${color};">${pct}%</span>
                        <div class="att-pct-bar"><div class="att-pct-fill" style="width:${pct}%;background:${color};"></div></div>
                    </td>
                </tr>`;
            }).join('');
        });
}

function loadODList() {
    fetch('attendance_api.php?action=student_od_list')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('odList');
            if (data.status !== 'success' || !data.requests.length) {
                list.innerHTML = '<div class="empty-state"><div class="empty-icon">📋</div><p>No OD/Leave requests yet.</p></div>';
                return;
            }
            list.innerHTML = data.requests.map(r => {
                const hoursStr = r.hours && r.hours.length ? 'Hours: ' + r.hours.join(', ') : '';
                const dateRange = r.date_from ? (r.date_to && r.date_to !== r.date_from ? `${r.date_from} - ${r.date_to}` : r.date_from) : '';
                return `<div class="od-item ${r.status}">
                    <div style="flex:1;">
                        <div class="od-type">${escapeHtml(r.type)} <span style="color:#888;font-weight:400;font-size:12px;">· ${escapeHtml(r.duration)}</span></div>
                        <div class="od-meta">${dateRange} ${hoursStr ? '· ' + hoursStr : ''} · ${r.created_at}</div>
                        ${r.reason ? `<div style="font-size:13px;color:#555;margin-top:4px;">${escapeHtml(r.reason)}</div>` : ''}
                        ${r.mentor_remarks ? `<div style="font-size:12px;color:#888;margin-top:4px;font-style:italic;">Mentor: ${escapeHtml(r.mentor_remarks)}</div>` : ''}
                    </div>
                    <span class="od-badge ${r.status}">${r.status}</span>
                </div>`;
            }).join('');
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

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
