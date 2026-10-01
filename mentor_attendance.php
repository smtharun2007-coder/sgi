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

        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; }
        .modal-box { background: #fff; border-radius: 20px; max-width: 520px; width: 90%; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalSlide 0.3s ease; }
        @keyframes modalSlide { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-box h3 { color: #1a1a2e; font-size: 19px; margin-bottom: 12px; }
        .modal-btn-row { display: flex; gap: 12px; margin-top: 20px; }
        .modal-btn { flex: 1; padding: 12px; border-radius: 10px; border: none; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .modal-btn.cancel { background: #eee; color: #555; }
        .modal-btn.cancel:hover { background: #e0e0e0; }

        .sem-lifecycle-card {
            background: #fff; border-radius: 16px; padding: 22px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06); border-top: 4px solid #eee;
            display: flex; flex-direction: column; justify-content: space-between;
            transition: all 0.3s ease; min-height: 180px;
        }
        .sem-lifecycle-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        .sem-lifecycle-card.status-open { border-top-color: #28a745; }
        .sem-lifecycle-card.status-closed { border-top-color: #6c757d; background: #fafbfc; }
        .sem-lifecycle-card.status-locked { border-top-color: #ffc107; background: #fffdf5; }

        .sem-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .sem-badge.open { background: #d4edda; color: #155724; }
        .sem-badge.closed { background: #e2e3e5; color: #383d41; }
        .sem-badge.locked { background: #fff3cd; color: #856404; }

        .btn-close-att {
            background: linear-gradient(135deg, #e53e3e, #c53030); color: #fff;
            border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
            cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-close-att:hover { background: #9b2c2c; box-shadow: 0 4px 12px rgba(197,48,48,0.3); }

        .btn-mark-att {
            background: #1a1a2e; color: #fff; text-decoration: none;
            padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-mark-att:hover { background: #8e44ad; }
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

    <!-- SEMESTER ATTENDANCE LIFECYCLE & CLOSE SECTION -->
    <div class="batch-selector" style="margin-top:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
            <div>
                <h3 style="margin:0;font-size:20px;color:#1a1a2e;display:flex;align-items:center;gap:8px;">
                    <span>🔒</span> Semester Attendance Lifecycle & Close Control
                </h3>
                <p style="margin:4px 0 0;font-size:13px;color:#666;">
                    Sequential semester control: Previous semesters must be explicitly closed before future semester attendance can be marked.
                </p>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <label style="margin:0;font-size:13px;font-weight:600;color:#555;">Batch:</label>
                <select id="lifecycleBatch" onchange="loadLifecycleStatus()" style="max-width:220px;padding:8px 14px;border-radius:10px;border:2px solid #e0e0e0;font-size:14px;background:#f8f9fa;">
                    <?php foreach($batches as $b): ?>
                    <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="lifecycleGrid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(270px, 1fr));gap:16px;margin-top:20px;">
            <div class="empty-state" style="grid-column:1/-1;">Loading semester attendance status...</div>
        </div>
    </div>
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

<!-- Close Semester Confirmation Modal -->
<div class="modal-overlay" id="closeSemesterModal">
    <div class="modal-box">
        <div style="font-size:38px;text-align:center;margin-bottom:12px;">🔒</div>
        <h3 style="text-align:center;margin-bottom:8px;" id="closeModalTitle">Close Semester Attendance</h3>
        <div style="background:#fff3cd;border:1px solid #ffeeba;border-radius:12px;padding:14px 16px;margin-bottom:16px;font-size:13px;color:#856404;line-height:1.5;">
            <strong>⚠️ CRITICAL ACTION:</strong> After closing, attendance marking, status modifications, session edits, and OD approvals for this semester will <strong>no longer be allowed</strong>. Records will become permanent and read-only.
        </div>
        <p style="font-size:13px;color:#555;margin-bottom:16px;line-height:1.4;" id="closeModalPrompt">
            Are you sure you want to close attendance for this semester?
        </p>
        <div style="margin-bottom:18px;">
            <label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;cursor:pointer;user-select:none;color:#333;">
                <input type="checkbox" id="closeConfirmCheck" style="margin-top:3px;cursor:pointer;">
                <span>I confirm that all attendance sessions for this semester have been completed and verified for finalization.</span>
            </label>
        </div>
        <div class="modal-btn-row">
            <button type="button" class="modal-btn cancel" onclick="closeSemesterModalClose()">Cancel</button>
            <button type="button" class="modal-btn" style="background:#dc3545;color:#fff;" id="btnConfirmCloseSem" onclick="executeCloseSemester()">Close Attendance</button>
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

let pendingCloseBatch = '';
let pendingCloseSem = 0;

function loadLifecycleStatus() {
    const batchSelect = document.getElementById('lifecycleBatch');
    const grid = document.getElementById('lifecycleGrid');
    if (!batchSelect || !batchSelect.value) {
        if (grid) grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1;">Please select a batch to view semester attendance lifecycle.</div>';
        return;
    }
    const batch = batchSelect.value;
    grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1;">Loading semester attendance lifecycle...</div>';

    fetch(`attendance_api.php?action=batch_semesters_status&batch=${encodeURIComponent(batch)}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;color:#dc3545;">Failed to load lifecycle status: ${escapeHtml(data.message || 'Error')}</div>`;
                return;
            }

            const semMap = data.semesters || {};
            let html = '';

            for (let s = 1; s <= 8; s++) {
                const info = semMap[s] || { status: (s === 1 ? 'OPEN' : 'LOCKED'), can_mark: (s === 1) };
                const st = info.status || 'OPEN';
                const isClosed = (st === 'CLOSED');
                const isLocked = (st === 'LOCKED');
                const isOpen = (st === 'OPEN');

                let cardClass = 'status-' + st.toLowerCase();
                let badgeClass = st.toLowerCase();

                let closedDateStr = '';
                if (info.closed_at) {
                    try {
                        const d = new Date(info.closed_at.date || info.closed_at);
                        closedDateStr = !isNaN(d.getTime()) ? d.toLocaleDateString('en-GB') : '';
                    } catch(e) {}
                }

                html += `<div class="sem-lifecycle-card ${cardClass}">
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <h4 style="margin:0;font-size:16px;color:#1a1a2e;font-weight:700;">Semester ${s}</h4>
                            <span class="sem-badge ${badgeClass}">${st}</span>
                        </div>`;

                if (isClosed) {
                    html += `
                        <div style="font-size:12px;color:#555;margin-bottom:12px;line-height:1.5;">
                            <div><strong>Closed by:</strong> ${escapeHtml(info.closed_by_name || info.closed_by || 'Admin/Mentor')}</div>
                            ${closedDateStr ? `<div><strong>Closed on:</strong> ${escapeHtml(closedDateStr)}</div>` : ''}
                            <div style="color:#6c757d;margin-top:6px;font-style:italic;">Attendance is finalized & read-only.</div>
                        </div>`;
                } else if (isLocked) {
                    html += `
                        <div style="font-size:12px;color:#856404;background:#fff3cd;padding:10px 12px;border-radius:8px;margin-bottom:12px;line-height:1.4;">
                            <strong>🔒 Attendance Locked</strong><br>
                            ${escapeHtml(info.reason || `Semester ${s-1} attendance has not been closed. Please close Semester ${s-1} attendance before marking Semester ${s} attendance.`)}
                        </div>`;
                } else {
                    html += `
                        <div style="font-size:12px;color:#155724;background:#d4edda;padding:10px 12px;border-radius:8px;margin-bottom:12px;line-height:1.4;">
                            <strong>✅ Attendance Open</strong><br>
                            Attendance sessions can be generated, marked, and modified.
                        </div>`;
                }

                html += `</div>
                    <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;align-items:center;">`;

                if (isOpen) {
                    html += `
                        <a href="mentor_attendance_calendar.php?batch=${encodeURIComponent(batch)}&semester=${s}" class="btn-mark-att">
                            Mark Attendance
                        </a>
                        <button type="button" class="btn-close-att" onclick="openCloseSemesterModal('${escapeHtml(batch)}', ${s})">
                            Close Attendance
                        </button>`;
                } else if (isClosed) {
                    html += `
                        <a href="mentor_attendance_calendar.php?batch=${encodeURIComponent(batch)}&semester=${s}" class="btn-mark-att" style="background:#6c757d;">
                            View Attendance
                        </a>
                        <button type="button" disabled style="background:#e2e3e5;color:#6c757d;border:none;padding:8px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:not-allowed;">
                            Attendance Closed
                        </button>`;
                } else {
                    html += `
                        <button type="button" disabled style="background:#f8f9fa;color:#aaa;border:1px solid #ddd;padding:8px 16px;border-radius:8px;font-size:12px;font-weight:600;cursor:not-allowed;">
                            Attendance Locked
                        </button>`;
                }

                html += `</div>
                </div>`;
            }

            grid.innerHTML = html;
        })
        .catch(err => {
            grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;color:#dc3545;">Network error loading lifecycle status.</div>`;
        });
}

function openCloseSemesterModal(batch, sem) {
    pendingCloseBatch = batch;
    pendingCloseSem = sem;
    document.getElementById('closeModalTitle').textContent = `Close Semester ${sem} Attendance`;
    document.getElementById('closeModalPrompt').innerHTML = `Are you sure you want to close attendance for <strong>Batch ${escapeHtml(batch)} — Semester ${sem}</strong>?`;
    document.getElementById('closeConfirmCheck').checked = false;
    document.getElementById('closeSemesterModal').classList.add('active');
}

function closeSemesterModalClose() {
    document.getElementById('closeSemesterModal').classList.remove('active');
    pendingCloseBatch = '';
    pendingCloseSem = 0;
}

function executeCloseSemester() {
    const check = document.getElementById('closeConfirmCheck');
    if (!check.checked) {
        showToast('Please check the confirmation box to proceed.', 'error');
        return;
    }
    if (!pendingCloseBatch || !pendingCloseSem) return;

    const btn = document.getElementById('btnConfirmCloseSem');
    btn.disabled = true;
    btn.textContent = 'Closing...';

    const formData = new FormData();
    formData.append('action', 'close_semester');
    formData.append('batch', pendingCloseBatch);
    formData.append('semester', pendingCloseSem);
    formData.append('confirm', '1');

    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Close Attendance';
            if (data.status === 'success') {
                showToast(data.message, 'success');
                closeSemesterModalClose();
                loadLifecycleStatus();
            } else {
                showToast(data.message || 'Failed to close semester', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = 'Close Attendance';
            showToast('Network error while closing semester', 'error');
        });
}

document.addEventListener('DOMContentLoaded', function() {
    loadLifecycleStatus();
});

function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

function toggleNotif() { const d = document.getElementById('notifDrop'); d.classList.toggle('open'); if (d.classList.contains('open')) loadNotifs(); }
function escNotifLink(u){return String(u||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function notifLinkHTML(n){return n.link?`<div style="margin-top:6px;"><a href="${escNotifLink(n.link)}" style="display:inline-block;padding:4px 12px;background:#e94560;color:#fff;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;" onclick="event.stopPropagation();">View &rarr;</a></div>`:'';}
function loadNotifs() { fetch('notifications.php?fetch=1&mentor=1').then(r=>r.json()).then(data=>{ const l=document.getElementById('notifList'); if(!data.length){l.innerHTML='<div class="notif-empty">No notifications</div>';return;} l.innerHTML=data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}${notifLinkHTML(n)}</div><div class="notif-time">${n.time}</div></div>`).join(''); }); }
function markAll(e) { e.preventDefault(); fetch('notifications.php?mark_all=1&mentor=1'); document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread')); const b=document.querySelector('.notif-badge'); if(b) b.remove(); }
function clearAll(e) { e.preventDefault(); fetch('notifications.php?delete_all=1&mentor=1'); document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>'; const b=document.querySelector('.notif-badge'); if(b) b.remove(); }
document.addEventListener('click', e => { const btn=document.getElementById('bellBtn'); const d=document.getElementById('notifDrop'); if(btn&&d&&!btn.contains(e.target)&&!d.contains(e.target)) d.classList.remove('open'); });
</script>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>
