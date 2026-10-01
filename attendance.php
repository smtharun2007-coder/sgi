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
            background: #fff; border-radius: 16px; padding: 22px;
            text-align: center; box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            border-top: 4px solid transparent;
            transition: all 0.3s ease;
        }
        .att-overview-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .att-overview-card.overall { border-top-color: #1a1a2e; }
        .att-overview-card.present { border-top-color: #28a745; }
        .att-overview-card.absent { border-top-color: #dc3545; }
        .att-overview-card.od { border-top-color: #17a2b8; }
        .att-overview-card.suspended { border-top-color: #6c757d; }
        .att-overview-card .att-num { font-size: 34px; font-weight: 700; color: #1a1a2e; }
        .att-overview-card .att-sub { font-size: 11px; color: #888; margin-top: 4px; }
        .att-overview-card .att-label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-top: 6px; }

        .att-section {
            background: #fff; border-radius: 16px; padding: 28px;
            margin-top: 24px; box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }
        .att-section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
        .att-section h3 { color: #1a1a2e; font-size: 18px; margin: 0; }

        .subj-table { width: 100%; border-collapse: collapse; }
        .subj-table th { text-align: left; font-size: 11px; color: #888; text-transform: uppercase; padding: 12px; border-bottom: 2px solid #eee; }
        .subj-table td { padding: 12px; font-size: 14px; border-bottom: 1px solid #f0f2f5; }
        .subj-table tr:last-child td { border-bottom: none; }

        .att-pct-bar { width: 100%; height: 8px; background: #f0f2f5; border-radius: 4px; overflow: hidden; margin-top: 4px; }
        .att-pct-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }

        .day-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .day-table th { text-align: left; font-size: 11px; color: #888; text-transform: uppercase; padding: 10px 12px; border-bottom: 2px solid #eee; }
        .day-table td { padding: 12px; font-size: 13px; border-bottom: 1px solid #f0f2f5; }
        .day-row-details { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
        .period-pill { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }

        .badge-status { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .badge-status.PRESENT { background: #d4edda; color: #155724; }
        .badge-status.ABSENT { background: #f8d7da; color: #721c24; }
        .badge-status.OD, .badge-status.OD_APPROVED { background: #d1ecf1; color: #0c5460; }
        .badge-status.OD_REJECTED { background: #ffeeba; color: #856404; }
        .badge-status.SUSPENDED { background: #e2e3e5; color: #383d41; }
        .badge-status.HOLIDAY { background: #f8d7da; color: #721c24; }
        .badge-status.FULL_PRESENT { background: #d4edda; color: #155724; }
        .badge-status.HALF_DAY, .badge-status.HALF_PRESENT { background: #fff3cd; color: #856404; }
        .badge-status.FULL_ABSENT { background: #f8d7da; color: #721c24; }
        .badge-status.EXCLUDED { background: #e2e3e5; color: #6c757d; }

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

        .btn-od {
            display: inline-block; padding: 10px 24px;
            background: linear-gradient(135deg, #1a1a2e, #e94560);
            color: #fff; border-radius: 10px; font-size: 14px; font-weight: 600;
            text-decoration: none; transition: all 0.3s;
        }
        .btn-od:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(233,69,96,0.3); }

        .empty-state { text-align: center; padding: 40px; color: #888; }
        .empty-state .empty-icon { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }

        .att-nav-links { display: flex; gap: 12px; margin-top: 20px; justify-content: center; flex-wrap: wrap; }
        .att-nav-btn {
            padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600;
            text-decoration: none; transition: all 0.2s; border: 2px solid #eee; background: #fff; color: #333; cursor: pointer;
        }
        .att-nav-btn:hover { border-color: #e94560; color: #e94560; }
        .att-nav-btn.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }

        .view-tab { display: none; }
        .view-tab.active { display: block; }
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
        <h1>Attendance Management</h1>
        <p>Institutional Daily Attendance (H1/H5 Rule), Subject-wise & Day-wise Tracking</p>
        <div style="margin-top:14px;display:flex;justify-content:center;gap:10px;align-items:center;flex-wrap:wrap;">
            <span id="studentSemBadge" style="background:rgba(255,255,255,0.2);padding:6px 16px;border-radius:20px;font-size:13px;font-weight:600;">Semester —</span>
            <span id="studentStatusBadge" style="background:#28a745;padding:6px 16px;border-radius:20px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Status: Loading...</span>
        </div>
    </div>

    <div class="att-nav-links">
        <button type="button" class="att-nav-btn active" id="tabBtnOverview" onclick="switchView('overview')">Overview & Subjects</button>
        <button type="button" class="att-nav-btn" id="tabBtnDaywise" onclick="switchView('daywise')">Day-wise Details</button>
        <a href="attendance_calendar.php" class="att-nav-btn">Calendar View</a>
        <a href="attendance_od_request.php" class="att-nav-btn">Apply OD / Leave</a>
    </div>

    <!-- TAB 1: OVERVIEW & SUBJECTS -->
    <div id="viewOverview" class="view-tab active">
        <div class="att-overview-row" id="overviewCards">
            <div class="att-overview-card overall">
                <div class="att-num" id="overallPct">—</div>
                <div class="att-label">Attendance %</div>
                <div class="att-sub" id="discScoreSub">Discipline: — / 5.0</div>
            </div>
            <div class="att-overview-card present">
                <div class="att-num" id="presentCount">—</div>
                <div class="att-label">Present</div>
                <div class="att-sub" id="presentDaysSub">— Days</div>
            </div>
            <div class="att-overview-card absent">
                <div class="att-num" id="absentCount">—</div>
                <div class="att-label">Absent</div>
                <div class="att-sub" id="absentDaysSub">— Days</div>
            </div>
            <div class="att-overview-card od">
                <div class="att-num" id="odCount">—</div>
                <div class="att-label">OD Approved</div>
                <div class="att-sub">Effective Present</div>
            </div>
            <div class="att-overview-card suspended">
                <div class="att-num" id="suspendedCount">—</div>
                <div class="att-label">Suspended</div>
                <div class="att-sub">Excluded from %</div>
            </div>
        </div>

        <div class="att-section">
            <div class="att-section-header">
                <h3>Subject-wise Attendance</h3>
                <span style="font-size:12px;color:#888;">(Calculated on actual conducted sessions for each subject)</span>
            </div>
            <table class="subj-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Code</th>
                        <th>Attended</th>
                        <th>Absent</th>
                        <th>Conducted</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody id="subjBody">
                    <tr><td colspan="6" style="text-align:center;color:#888;">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="att-section">
            <div class="att-section-header">
                <h3>OD / Leave History</h3>
                <a href="attendance_od_request.php" class="btn-od">+ Apply for OD / Leave</a>
            </div>
            <div class="od-list" id="odList">
                <div class="empty-state"><div class="empty-icon">📋</div><p>Loading...</p></div>
            </div>
        </div>
    </div>

    <!-- TAB 2: DAY-WISE ATTENDANCE -->
    <div id="viewDaywise" class="view-tab">
        <div class="att-section">
            <div class="att-section-header">
                <h3>Day-wise Attendance & Timetable Periods</h3>
                <span style="font-size:12px;color:#888;">(Daily attendance determined by Morning H1 + Afternoon H5 rules)</span>
            </div>
            <table class="day-table">
                <thead>
                    <tr>
                        <th style="width:140px;">Date</th>
                        <th style="width:130px;">Daily Status</th>
                        <th style="width:120px;">Morning (H1)</th>
                        <th style="width:120px;">Afternoon (H5)</th>
                        <th>Timetable Sessions & Attendance</th>
                    </tr>
                </thead>
                <tbody id="daywiseBody">
                    <tr><td colspan="5" style="text-align:center;color:#888;">Loading day-wise attendance...</td></tr>
                </tbody>
            </table>
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

function switchView(tab) {
    document.querySelectorAll('.view-tab').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.att-nav-btn').forEach(el => {
        if (el.id === 'tabBtnOverview' || el.id === 'tabBtnDaywise') el.classList.remove('active');
    });

    if (tab === 'daywise') {
        document.getElementById('viewDaywise').classList.add('active');
        document.getElementById('tabBtnDaywise').classList.add('active');
        loadDaywise();
    } else {
        document.getElementById('viewOverview').classList.add('active');
        document.getElementById('tabBtnOverview').classList.add('active');
    }
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

            if (data.semester) {
                document.getElementById('studentSemBadge').textContent = `Semester ${data.semester}`;
            }
            const statusEl = document.getElementById('studentStatusBadge');
            if (data.is_closed) {
                statusEl.textContent = 'Status: CLOSED (Finalized)';
                statusEl.style.background = '#6c757d';
                statusEl.style.color = '#fff';
            } else if (data.is_locked) {
                statusEl.textContent = 'Status: LOCKED';
                statusEl.style.background = '#ffc107';
                statusEl.style.color = '#333';
            } else {
                statusEl.textContent = 'Status: OPEN (Active)';
                statusEl.style.background = '#28a745';
                statusEl.style.color = '#fff';
            }

            document.getElementById('overallPct').textContent = data.overall + '%';
            document.getElementById('discScoreSub').textContent = 'Discipline: ' + (data.discipline_score !== undefined ? data.discipline_score : (data.overall/20).toFixed(2)) + ' / 5.0';
            document.getElementById('presentCount').textContent = data.present;
            document.getElementById('presentDaysSub').textContent = (data.present_days || 0) + ' Days Present';
            document.getElementById('absentCount').textContent = data.absent;
            document.getElementById('absentDaysSub').textContent = (data.absent_days || 0) + ' Days Absent';
            document.getElementById('odCount').textContent = data.od;
            document.getElementById('suspendedCount').textContent = data.suspended || 0;

            const body = document.getElementById('subjBody');
            if (!data.subjects || !data.subjects.length) {
                body.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#888;">No attendance sessions conducted yet.</td></tr>';
                return;
            }
            body.innerHTML = data.subjects.map(s => {
                const pct = s.conducted > 0 ? Math.round((s.attended / s.conducted) * 100, 2) : 0;
                const color = pct >= 80 ? '#28a745' : pct >= 60 ? '#f5a623' : '#dc3545';
                return `<tr>
                    <td><strong>${escapeHtml(s.subject)}</strong></td>
                    <td style="color:#888;">${escapeHtml(s.code || '—')}</td>
                    <td><span style="color:#28a745;font-weight:600;">${s.attended}</span></td>
                    <td><span style="color:#dc3545;font-weight:600;">${s.absent}</span></td>
                    <td>${s.conducted}</td>
                    <td>
                        <span style="font-weight:700;color:${color};">${pct}%</span>
                        <div class="att-pct-bar"><div class="att-pct-fill" style="width:${pct}%;background:${color};"></div></div>
                    </td>
                </tr>`;
            }).join('');
        });
}

function loadDaywise() {
    fetch('attendance_api.php?action=student_daywise')
        .then(r => r.json())
        .then(data => {
            const body = document.getElementById('daywiseBody');
            if (data.status !== 'success' || !data.daily_breakdown || !Object.keys(data.daily_breakdown).length) {
                body.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888;">No day-wise attendance data available yet.</td></tr>';
                return;
            }

            const dates = Object.keys(data.daily_breakdown).sort().reverse();
            body.innerHTML = dates.map(dStr => {
                const day = data.daily_breakdown[dStr];
                const sessions = day.sessions || [];

                let dayStatusCls = day.day_status;
                let dayStatusText = day.day_status.replace('_', ' ');
                if (day.day_status === 'FULL_PRESENT') dayStatusText = 'Full Day (1.0)';
                else if (day.day_status === 'HALF_DAY' || day.day_status === 'HALF_PRESENT') dayStatusText = 'Half Day (0.5)';
                else if (day.day_status === 'FULL_ABSENT') dayStatusText = 'Absent (0.0)';
                else if (day.day_status === 'EXCLUDED') dayStatusText = 'Excluded / Holiday';

                const morningBadge = `<span class="badge-status ${day.morning_status}">${day.morning_status}</span>`;
                const afternoonBadge = `<span class="badge-status ${day.afternoon_status}">${day.afternoon_status}</span>`;

                const periodPills = sessions.map(s => {
                    let pillBg = '#f0f2f5', pillColor = '#555';
                    let st = s.effective || s.student_status;
                    if (s.session_status === 'SUSPENDED') {
                        pillBg = '#e2e3e5'; pillColor = '#383d41'; st = 'SUSPENDED';
                    } else if (st === 'PRESENT') {
                        pillBg = '#d4edda'; pillColor = '#155724';
                    } else if (st === 'OD') {
                        pillBg = '#d1ecf1'; pillColor = '#0c5460'; st = 'OD Approved';
                    } else if (st === 'ABSENT') {
                        pillBg = '#f8d7da'; pillColor = '#721c24';
                    }
                    return `<span class="period-pill" style="background:${pillBg};color:${pillColor};">
                        <strong>H${s.hour}</strong>: ${escapeHtml(s.subject)} (${st})
                    </span>`;
                }).join('');

                return `<tr>
                    <td><strong>${dStr}</strong></td>
                    <td><span class="badge-status ${dayStatusCls}">${dayStatusText}</span></td>
                    <td>${morningBadge}</td>
                    <td>${afternoonBadge}</td>
                    <td><div class="day-row-details">${periodPills || '<span style="color:#aaa;">No scheduled periods</span>'}</div></td>
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
                list.innerHTML = '<div class="empty-state"><div class="empty-icon">📋</div><p>No OD/Leave requests submitted yet.</p></div>';
                return;
            }
            list.innerHTML = data.requests.map(r => {
                const hoursStr = r.hours && r.hours.length ? 'Hours: H' + r.hours.join(', H') : '';
                const halfStr = r.half_day_type ? '(' + r.half_day_type.toUpperCase() + ')' : '';
                const dateRange = r.date_from ? (r.date_to && r.date_to !== r.date_from ? `${r.date_from} - ${r.date_to}` : r.date_from) : '';
                const badgeCls = r.status === 'approved' ? 'OD_APPROVED' : (r.status === 'rejected' ? 'OD_REJECTED' : 'HALF_DAY');
                return `<div class="od-item ${r.status}">
                    <div style="flex:1;">
                        <div class="od-type">${escapeHtml(r.type)} <span style="color:#888;font-weight:400;font-size:12px;">· ${escapeHtml(r.duration.replace('_',' '))} ${halfStr}</span></div>
                        <div class="od-meta">${dateRange} ${hoursStr ? '· ' + hoursStr : ''} · Submitted: ${r.created_at}</div>
                        ${r.reason ? `<div style="font-size:13px;color:#555;margin-top:4px;">${escapeHtml(r.reason)}</div>` : ''}
                        ${r.mentor_remarks ? `<div style="font-size:12px;color:#888;margin-top:4px;font-style:italic;">Mentor: ${escapeHtml(r.mentor_remarks)}</div>` : ''}
                    </div>
                    <span class="badge-status ${badgeCls}">${r.status === 'approved' ? 'OD Approved' : (r.status === 'rejected' ? 'OD Rejected' : 'Pending')}</span>
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
function escNotifLink(u){return String(u||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function notifLinkHTML(n){return n.link?`<div style="margin-top:6px;"><a href="${escNotifLink(n.link)}" style="display:inline-block;padding:4px 12px;background:#e94560;color:#fff;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;" onclick="event.stopPropagation();">View &rarr;</a></div>`:'';}
function loadNotifs() { fetch('notifications.php?fetch=1').then(r=>r.json()).then(data=>{ const l=document.getElementById('notifList'); if(!data.length){l.innerHTML='<div class="notif-empty">No notifications</div>';return;} l.innerHTML=data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}${notifLinkHTML(n)}</div><div class="notif-time">${n.time}</div></div>`).join(''); }); }
function markAll(e) { e.preventDefault(); fetch('notifications.php?mark_all=1'); document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread')); const b=document.querySelector('.notif-badge'); if(b) b.remove(); }
function clearAll(e) { e.preventDefault(); fetch('notifications.php?delete_all=1'); document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>'; const b=document.querySelector('.notif-badge'); if(b) b.remove(); }
document.addEventListener('click', e => { const btn=document.getElementById('bellBtn'); const d=document.getElementById('notifDrop'); if(btn&&d&&!btn.contains(e.target)&&!d.contains(e.target)) d.classList.remove('open'); });
</script>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>
