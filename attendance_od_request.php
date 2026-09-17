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
    <title>SGI – Apply OD / Leave</title>
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

        .od-form-box { background: #fff; border-radius: 16px; padding: 36px; margin-top: 24px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); max-width: 700px; margin-left: auto; margin-right: auto; }
        .od-form-box h3 { color: #1a1a2e; font-size: 18px; margin-bottom: 20px; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        .form-group select, .form-group input, .form-group textarea {
            width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 10px;
            font-size: 14px; background: #f8f9fa; transition: all 0.3s; box-sizing: border-box;
        }
        .form-group select:focus, .form-group input:focus, .form-group textarea:focus {
            border-color: #e94560; background: #fff; outline: none;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }

        .hour-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .hour-chip {
            padding: 10px; border: 2px solid #e0e0e0; border-radius: 10px; text-align: center;
            cursor: pointer; transition: all 0.2s; font-size: 14px; font-weight: 600; background: #fff;
        }
        .hour-chip.selected { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }
        .hour-chip:hover:not(.selected) { border-color: #e94560; }

        .btn-submit {
            display: inline-block; width: 100%; padding: 14px 20px;
            background: linear-gradient(135deg, #e94560, #c73e54); color: #fff;
            border: none; border-radius: 12px; font-size: 16px; font-weight: 600;
            cursor: pointer; margin-top: 16px; transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(233,69,96,0.3);
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(233,69,96,0.4); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .od-list { margin-top: 24px; }
        .od-item { background: #f8f9fa; border-radius: 12px; padding: 16px 20px; margin-bottom: 12px; display: flex; align-items: center; gap: 16px; border-left: 4px solid transparent; }
        .od-item.pending { border-left-color: #ffc107; }
        .od-item.approved { border-left-color: #28a745; }
        .od-item.rejected { border-left-color: #dc3545; }
        .od-type { font-size: 14px; font-weight: 600; color: #1a1a2e; }
        .od-meta { font-size: 12px; color: #888; margin-top: 4px; }
        .od-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; flex-shrink: 0; }
        .od-badge.pending { background: #fff3cd; color: #856404; }
        .od-badge.approved { background: #d4edda; color: #155724; }
        .od-badge.rejected { background: #f8d7da; color: #721c24; }
        .empty-state { text-align: center; padding: 40px; color: #888; }
        .empty-state .empty-icon { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }
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
        <h1>Apply for OD / Leave</h1>
        <p>Submit your On-Duty, Leave, or Regularization request for mentor approval</p>
    </div>

    <div class="att-nav-links">
        <a href="attendance.php" class="att-nav-btn">Overview</a>
        <a href="attendance_calendar.php" class="att-nav-btn">Attendance Calendar</a>
        <a href="attendance_od_request.php" class="att-nav-btn active">Apply OD / Leave</a>
    </div>

    <div class="od-form-box">
        <h3>New OD / Leave Request</h3>
        <form id="odForm" onsubmit="submitOD(event)">
            <div class="form-group">
                <label>Type</label>
                <select name="od_type" id="odType" required>
                    <option value="">Select type...</option>
                    <option value="Paper Presentation">Paper Presentation</option>
                    <option value="Hackathon">Hackathon</option>
                    <option value="Workshop/Seminar">Workshop / Seminar</option>
                    <option value="Competition">Competition</option>
                    <option value="Sports/Cultural Event">Sports / Cultural Event</option>
                    <option value="Medical Leave">Medical Leave</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Duration</label>
                <select name="duration" id="duration" onchange="toggleDurationFields()" required>
                    <option value="">Select duration...</option>
                    <option value="full_day">Full Day</option>
                    <option value="half_day">Half Day</option>
                    <option value="select_hours">Select Hours</option>
                </select>
            </div>

            <div id="dateRangeFields" style="display:none;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" id="dateFrom">
                    </div>
                    <div class="form-group">
                        <label>To Date (leave same for single day)</label>
                        <input type="date" name="date_to" id="dateTo">
                    </div>
                </div>
            </div>

            <div id="hoursFields" style="display:none;">
                <div class="form-group">
                    <label>Select Hours (click to toggle, can select multiple)</label>
                    <div class="hour-grid" id="hourGrid">
                        <div class="hour-chip" data-hour="1" onclick="toggleHour(this)">H1</div>
                        <div class="hour-chip" data-hour="2" onclick="toggleHour(this)">H2</div>
                        <div class="hour-chip" data-hour="3" onclick="toggleHour(this)">H3</div>
                        <div class="hour-chip" data-hour="4" onclick="toggleHour(this)">H4</div>
                        <div class="hour-chip" data-hour="5" onclick="toggleHour(this)">H5</div>
                        <div class="hour-chip" data-hour="6" onclick="toggleHour(this)">H6</div>
                        <div class="hour-chip" data-hour="7" onclick="toggleHour(this)">H7</div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Reason</label>
                <textarea name="reason" id="reason" placeholder="Provide details for your request..." required></textarea>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">Submit Request</button>
        </form>
    </div>

    <div class="od-list" id="odList">
        <div class="empty-state"><div class="empty-icon">📋</div><p>Loading...</p></div>
    </div>
</div>
<script>
let selectedHours = [];

function toggleDurationFields() {
    const dur = document.getElementById('duration').value;
    document.getElementById('dateRangeFields').style.display = (dur === 'full_day' || dur === 'half_day') ? 'block' : 'none';
    document.getElementById('hoursFields').style.display = (dur === 'select_hours') ? 'block' : 'none';
}

function toggleHour(chip) {
    const hour = chip.dataset.hour;
    chip.classList.toggle('selected');
    if (chip.classList.contains('selected')) {
        if (!selectedHours.includes(hour)) selectedHours.push(hour);
    } else {
        selectedHours = selectedHours.filter(h => h !== hour);
    }
}

function submitOD(event) {
    event.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Submitting...';

    const formData = new FormData();
    formData.append('action', 'submit_od');
    formData.append('od_type', document.getElementById('odType').value);
    formData.append('duration', document.getElementById('duration').value);
    formData.append('date_from', document.getElementById('dateFrom').value);
    formData.append('date_to', document.getElementById('dateTo').value);
    formData.append('reason', document.getElementById('reason').value);
    selectedHours.forEach(h => formData.append('hours[]', h));

    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Submit Request';
            if (data.status === 'success') {
                showToast(data.message, 'success');
                document.getElementById('odForm').reset();
                selectedHours = [];
                document.querySelectorAll('.hour-chip.selected').forEach(c => c.classList.remove('selected'));
                toggleDurationFields();
                loadODList();
            } else {
                showToast(data.message || 'Failed to submit', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = 'Submit Request';
            showToast('Network error', 'error');
        });
}

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

document.addEventListener('DOMContentLoaded', function() { loadODList(); });

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

function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

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
