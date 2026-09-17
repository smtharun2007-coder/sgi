<?php
include 'config.php';
if (!isset($_SESSION['mentor'])) { header("Location: mentor_login.php"); exit; }
$m = $_SESSION['mentor'];
$unreadCount = $notifications->countDocuments(['mentor_id'=>$m['mentor_id'],'read'=>false]);

$studentCursor = $users->find(['mentor_id' => $m['mentor_id']]);
$batches = [];
foreach ($studentCursor as $s) {
    $b = $s['batch_no'] ?? '';
    if ($b && !in_array($b, $batches)) $batches[] = $b;
}
sort($batches);

$selBatch = $_GET['batch'] ?? ($batches[0] ?? '');
$selSem = (int)($_GET['semester'] ?? 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGI – Timetable</title>
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

        .tt-controls { background: #fff; border-radius: 16px; padding: 24px; margin-top: 24px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; }
        .tt-controls > div { flex: 1; min-width: 150px; }
        .tt-controls label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }

        .tt-grid-wrap { background: #fff; border-radius: 16px; padding: 24px; margin-top: 24px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); overflow-x: auto; }
        .tt-grid { display: grid; grid-template-columns: 80px repeat(7, 1fr); gap: 4px; min-width: 900px; }
        .tt-header { background: #1a1a2e; color: #fff; padding: 10px; text-align: center; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .tt-hour-label { background: #f0f2f5; padding: 10px; text-align: center; border-radius: 8px; font-size: 12px; font-weight: 600; color: #555; display: flex; flex-direction: column; justify-content: center; }
        .tt-hour-label .tt-time { font-size: 10px; color: #888; font-weight: 400; }
        .tt-slot { background: #f8f9fa; border-radius: 8px; padding: 8px; min-height: 60px; cursor: pointer; transition: all 0.2s; border: 2px solid transparent; }
        .tt-slot:hover { border-color: #8e44ad; }
        .tt-slot.filled { background: #e7f3ff; }
        .tt-slot .tt-subject { font-size: 12px; font-weight: 600; color: #1a1a2e; }
        .tt-slot .tt-code { font-size: 10px; color: #888; }
        .tt-slot .tt-faculty { font-size: 10px; color: #aaa; margin-top: 2px; }
        .tt-slot .tt-delete { font-size: 14px; color: #dc3545; cursor: pointer; float: right; }

        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; }
        .modal-box { background: #fff; border-radius: 20px; max-width: 500px; width: 90%; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalSlide 0.3s ease; }
        @keyframes modalSlide { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-box h3 { color: #1a1a2e; font-size: 18px; margin-bottom: 20px; }
        .modal-box .form-group { margin-bottom: 16px; }
        .modal-box .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        .modal-box .form-group input, .modal-box .form-group select { width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; background: #f8f9fa; box-sizing: border-box; }
        .modal-box .form-group input:focus, .modal-box .form-group select:focus { border-color: #8e44ad; background: #fff; outline: none; }
        .modal-btn-row { display: flex; gap: 12px; margin-top: 20px; }
        .modal-btn { flex: 1; padding: 12px; border-radius: 10px; border: none; font-size: 14px; font-weight: 600; cursor: pointer; }
        .modal-btn.save { background: linear-gradient(135deg, #1a1a2e, #8e44ad); color: #fff; }
        .modal-btn.cancel { background: #eee; color: #555; }
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
        <h1>Timetable Setup</h1>
        <p>Define weekly class schedules for each batch and semester</p>
    </div>

    <div class="att-nav-links">
        <a href="mentor_attendance.php" class="att-nav-btn">Dashboard</a>
        <a href="mentor_timetable.php" class="att-nav-btn active">Timetable</a>
        <a href="mentor_attendance_calendar.php" class="att-nav-btn">Calendar & Mark</a>
        <a href="mentor_attendance_od.php" class="att-nav-btn">OD / Leave Review</a>
    </div>

    <div class="tt-controls">
        <div>
            <label>Batch</label>
            <select id="ttBatch" onchange="loadTimetable()">
                <option value="">Select batch...</option>
                <?php foreach($batches as $b): ?>
                <option value="<?= htmlspecialchars($b) ?>" <?= $selBatch === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Semester</label>
            <select id="ttSemester" onchange="loadTimetable()">
                <?php for($s=1;$s<=8;$s++): ?>
                <option value="<?= $s ?>" <?= $selSem === $s ? 'selected' : '' ?>>Semester <?= $s ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>

    <div class="tt-grid-wrap">
        <div class="tt-grid" id="ttGrid"></div>
    </div>
</div>

<div class="modal-overlay" id="slotModal">
    <div class="modal-box">
        <h3 id="modalTitle">Add Class</h3>
        <div class="form-group">
            <label>Subject Name</label>
            <input type="text" id="slotSubject" placeholder="e.g. Data Structures">
        </div>
        <div class="form-group">
            <label>Subject Code</label>
            <input type="text" id="slotCode" placeholder="e.g. CS201">
        </div>
        <div class="form-group">
            <label>Faculty Name</label>
            <input type="text" id="slotFaculty" placeholder="e.g. Dr. Smith">
        </div>
        <div class="modal-btn-row">
            <button class="modal-btn cancel" onclick="closeModal()">Cancel</button>
            <button class="modal-btn save" onclick="saveSlot()">Save</button>
        </div>
    </div>
</div>

<script>
const HOURS = {
    1: {start: '08:45', end: '09:35'},
    2: {start: '09:35', end: '10:25'},
    3: {start: '10:45', end: '11:35'},
    4: {start: '11:35', end: '12:25'},
    5: {start: '13:25', end: '14:15'},
    6: {start: '14:15', end: '15:05'},
    7: {start: '15:25', end: '16:15'},
};
const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
let timetable = {};
let editingDay = -1, editingHour = -1, editingId = null;

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('ttBatch').value) loadTimetable();
    else renderGrid();
});

function loadTimetable() {
    const batch = document.getElementById('ttBatch').value;
    const semester = document.getElementById('ttSemester').value;
    if (!batch) { renderGrid(); return; }

    fetch(`attendance_api.php?action=timetable&batch=${encodeURIComponent(batch)}&semester=${semester}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') { renderGrid(); return; }
            timetable = {};
            data.timetable.forEach(t => {
                const key = `${t.day}_${t.hour}`;
                timetable[key] = t;
            });
            renderGrid();
        });
}

function renderGrid() {
    let html = '<div class="tt-header">Hour</div>';
    DAYS.forEach(d => html += `<div class="tt-header">${d}</div>`);

    for (let h = 1; h <= 7; h++) {
        html += `<div class="tt-hour-label"><div>H${h}</div><div class="tt-time">${HOURS[h].start}</div></div>`;
        for (let d = 0; d <= 6; d++) {
            const key = `${d}_${h}`;
            const slot = timetable[key];
            if (slot) {
                html += `<div class="tt-slot filled" onclick="editSlot(${d}, ${h}, '${slot._id}')">
                    <span class="tt-delete" onclick="event.stopPropagation();deleteSlot('${slot._id}')">&times;</span>
                    <div class="tt-subject">${escapeHtml(slot.subject)}</div>
                    <div class="tt-code">${escapeHtml(slot.subject_code)}</div>
                    <div class="tt-faculty">${escapeHtml(slot.faculty)}</div>
                </div>`;
            } else {
                html += `<div class="tt-slot" onclick="openModal(${d}, ${h})"><span style="color:#ccc;font-size:20px;">+</span></div>`;
            }
        }
    }
    document.getElementById('ttGrid').innerHTML = html;
}

function openModal(day, hour) {
    editingDay = day; editingHour = hour; editingId = null;
    document.getElementById('modalTitle').textContent = `Add Class - ${DAYS[day]} H${hour}`;
    document.getElementById('slotSubject').value = '';
    document.getElementById('slotCode').value = '';
    document.getElementById('slotFaculty').value = '';
    document.getElementById('slotModal').classList.add('active');
}

function editSlot(day, hour, id) {
    const key = `${day}_${hour}`;
    const slot = timetable[key];
    if (!slot) return;
    editingDay = day; editingHour = hour; editingId = id;
    document.getElementById('modalTitle').textContent = `Edit Class - ${DAYS[day]} H${hour}`;
    document.getElementById('slotSubject').value = slot.subject || '';
    document.getElementById('slotCode').value = slot.subject_code || '';
    document.getElementById('slotFaculty').value = slot.faculty || '';
    document.getElementById('slotModal').classList.add('active');
}

function closeModal() { document.getElementById('slotModal').classList.remove('active'); }

function saveSlot() {
    const batch = document.getElementById('ttBatch').value;
    const semester = document.getElementById('ttSemester').value;
    if (!batch) { showToast('Please select a batch first', 'error'); return; }

    const subject = document.getElementById('slotSubject').value.trim();
    const code = document.getElementById('slotCode').value.trim();
    const faculty = document.getElementById('slotFaculty').value.trim();
    if (!subject) { showToast('Subject name is required', 'error'); return; }

    const formData = new FormData();
    formData.append('action', 'save_timetable');
    formData.append('batch', batch);
    formData.append('semester', semester);
    formData.append('day', editingDay);
    formData.append('hour', editingHour);
    formData.append('subject', subject);
    formData.append('subject_code', code);
    formData.append('faculty', faculty);

    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                closeModal();
                loadTimetable();
            } else {
                showToast(data.message || 'Failed to save', 'error');
            }
        });
}

function deleteSlot(id) {
    if (!confirm('Delete this timetable entry?')) return;
    const formData = new FormData();
    formData.append('action', 'delete_timetable');
    formData.append('id', id);
    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') { showToast('Deleted', 'success'); loadTimetable(); }
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
document.getElementById('slotModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });

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
