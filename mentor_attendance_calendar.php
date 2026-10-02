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
$today = date('Y-m-d');
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
        .att-hero { background: linear-gradient(135deg, #1a1a2e, #8e44ad); padding: 40px 24px; border-radius: 20px; margin-top: 24px; text-align: center; color: #fff; }
        .att-hero h1 { margin: 0 0 8px; font-size: 28px; }
        .att-hero p { margin: 0; opacity: 0.9; font-size: 15px; }

        .att-nav-links { display: flex; gap: 12px; margin-top: 20px; justify-content: center; flex-wrap: wrap; }
        .att-nav-btn { padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.2s; border: 2px solid #eee; background: #fff; color: #333; }
        .att-nav-btn:hover { border-color: #8e44ad; color: #8e44ad; }
        .att-nav-btn.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }

        .cal-box { background: #fff; border-radius: 16px; padding: 28px; margin-top: 24px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
        .cal-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .cal-nav h2 { color: #1a1a2e; font-size: 20px; }
        .cal-nav-btn { padding: 8px 18px; background: #f0f2f5; border-radius: 10px; text-decoration: none; color: #333; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .cal-nav-btn:hover { background: #1a1a2e; color: #fff; }

        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .cal-day-name { text-align: center; font-size: 12px; font-weight: 600; color: #888; padding: 8px 4px; text-transform: uppercase; }
        .cal-cell { min-height: 75px; border-radius: 12px; border: 2px solid #f0f2f5; padding: 8px; cursor: pointer; transition: all 0.2s; position: relative; background: #fff; }
        .cal-cell.empty { border: none; cursor: default; background: transparent !important; }
        .cal-cell:hover:not(.empty) { border-color: #8e44ad; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .cal-cell.today { outline: 3px solid #8e44ad; }
        .cal-cell.status-marked { background: #d1fae5 !important; border-color: #10b981 !important; }
        .cal-cell.status-marked .cal-count { color: #047857; font-weight: 700; }
        .cal-cell.status-partial { background: #fef3c7 !important; border-color: #f59e0b !important; }
        .cal-cell.status-partial .cal-count { color: #b45309; font-weight: 700; }
        .cal-cell.status-scheduled { background: #e0e7ff !important; border-color: #6366f1 !important; }
        .cal-cell.status-scheduled .cal-count { color: #4338ca; font-weight: 600; }
        .cal-cell.status-holiday { background: #fee2e2 !important; border-color: #f87171 !important; }
        .cal-cell.status-holiday .cal-count { color: #dc2626; font-weight: 700; }
        .cal-cell.status-suspended { background: #f3f4f6 !important; border-color: #9ca3af !important; }
        .cal-date { font-size: 14px; font-weight: 700; color: #1a1a2e; }
        .cal-count { font-size: 10px; color: #888; margin-top: 4px; line-height: 1.2; }

        .cal-legend { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; padding: 10px 16px; background: #f8fafc; border-radius: 12px; }
        .legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #475569; }
        .legend-dot { width: 12px; height: 12px; border-radius: 4px; display: inline-block; }
        .legend-dot.marked { background: #10b981; }
        .legend-dot.partial { background: #f59e0b; }
        .legend-dot.scheduled { background: #6366f1; }
        .legend-dot.holiday { background: #ef4444; }

        .actions-row { display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap; }
        .action-btn { padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; }
        .action-btn.gen { background: linear-gradient(135deg, #1a1a2e, #8e44ad); color: #fff; }
        .action-btn.holiday { background: #ffc107; color: #333; }
        .action-btn.special { background: #17a2b8; color: #fff; }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,0.15); }

        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; }
        .modal-box { background: #fff; border-radius: 20px; max-width: 600px; width: 90%; max-height: 85vh; overflow-y: auto; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalSlide 0.3s ease; }
        @keyframes modalSlide { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-box h3 { color: #1a1a2e; font-size: 18px; margin-bottom: 20px; }
        .modal-box .form-group { margin-bottom: 16px; }
        .modal-box .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        .modal-box .form-group input, .modal-box .form-group select, .modal-box .form-group textarea { width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px; background: #f8f9fa; box-sizing: border-box; }
        .modal-box .form-group input:focus, .modal-box .form-group select:focus { border-color: #8e44ad; background: #fff; outline: none; }
        .modal-btn-row { display: flex; gap: 12px; margin-top: 20px; }
        .modal-btn { flex: 1; padding: 12px; border-radius: 10px; border: none; font-size: 14px; font-weight: 600; cursor: pointer; }
        .modal-btn.save { background: linear-gradient(135deg, #1a1a2e, #8e44ad); color: #fff; }
        .modal-btn.cancel { background: #eee; color: #555; }

        .session-list { margin-top: 16px; }
        .session-item { background: #f8f9fa; border-radius: 12px; padding: 16px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; }
        .session-info .s-hour { font-size: 14px; font-weight: 600; color: #1a1a2e; }
        .session-info .s-subject { font-size: 13px; color: #555; }
        .session-info .s-meta { font-size: 11px; color: #888; margin-top: 4px; }
        .session-actions { display: flex; gap: 8px; }
        .sess-btn { padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .sess-btn.mark { background: #28a745; color: #fff; }
        .sess-btn.mark:hover { background: #218838; }
        .sess-btn.suspend { background: #ffc107; color: #333; }
        .sess-btn.substitute { background: #17a2b8; color: #fff; }
        .sess-btn.reschedule { background: #6c757d; color: #fff; }
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
        <h1>Attendance Calendar</h1>
        <p>Click any date to generate sessions, mark attendance, or manage classes</p>
    </div>

    <div class="att-nav-links">
        <a href="mentor_attendance.php" class="att-nav-btn">Dashboard</a>
        <a href="mentor_timetable.php" class="att-nav-btn">Timetable</a>
        <a href="mentor_attendance_calendar.php" class="att-nav-btn active">Calendar & Mark</a>
        <a href="mentor_attendance_od.php" class="att-nav-btn">OD / Leave Review</a>
    </div>

    <div class="cal-box">
        <div class="cal-nav">
            <a href="mentor_attendance_calendar.php?month=<?= $prev['month'] ?>&year=<?= $prev['year'] ?>" class="cal-nav-btn">&#8592; Prev</a>
            <h2><?= $monthName ?></h2>
            <a href="mentor_attendance_calendar.php?month=<?= $next['month'] ?>&year=<?= $next['year'] ?>" class="cal-nav-btn">Next &#8594;</a>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;">
            <div class="cal-legend">
                <div class="legend-item"><span class="legend-dot marked"></span> Marked (Conducted)</div>
                <div class="legend-item"><span class="legend-dot partial"></span> Partially Marked</div>
                <div class="legend-item"><span class="legend-dot scheduled"></span> Scheduled (Pending)</div>
                <div class="legend-item"><span class="legend-dot holiday"></span> Holiday / Off</div>
            </div>
            <div>
                <button type="button" class="action-btn gen" onclick="openBulkGenModal()" style="font-size:13px;padding:8px 16px;display:flex;align-items:center;gap:6px;">
                    ⚡ Auto-Generate from Timetable
                </button>
            </div>
        </div>

        <div class="cal-grid">
            <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                <div class="cal-day-name"><?= $d ?></div>
            <?php endforeach; ?>
            <?php for($i=0;$i<$startDow;$i++): ?>
                <div class="cal-cell empty"></div>
            <?php endfor; ?>
            <?php
            $todayDay = (int)date('j'); $todayM = (int)date('n'); $todayY = (int)date('Y');
            for($d=1;$d<=$daysInMonth;$d++):
                $isToday = ($d==$todayDay && $month==$todayM && $year==$todayY);
                $dateVal = sprintf('%04d-%02d-%02d', $year, $month, $d);
            ?>
            <div class="cal-cell <?= $isToday?'today':'' ?>" id="cal-cell-<?= $d ?>" onclick="showDateDetail('<?= $dateVal ?>')">
                <div class="cal-date"><?= $d ?></div>
                <div class="cal-count" id="cal-<?= $d ?>"></div>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- Date Detail Modal -->
<div class="modal-overlay" id="dateModal">
    <div class="modal-box">
        <h3 id="dateModalTitle">Date Details</h3>
        <div id="dateModalBody"></div>
        <div class="modal-btn-row">
            <button class="modal-btn cancel" onclick="closeDateModal()">Close</button>
        </div>
    </div>
</div>

<!-- Generate Sessions Modal -->
<div class="modal-overlay" id="genModal">
    <div class="modal-box">
        <h3>Generate Attendance Sessions</h3>
        <div class="form-group">
            <label>Date</label>
            <input type="date" id="genDate" readonly>
        </div>
        <div class="form-group">
            <label>Batch</label>
            <select id="genBatch">
                <option value="">Select batch...</option>
                <?php foreach($batches as $b): ?>
                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Semester</label>
            <select id="genSemester">
                <?php for($s=1;$s<=8;$s++): ?>
                <option value="<?= $s ?>">Semester <?= $s ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="modal-btn-row">
            <button class="modal-btn cancel" onclick="closeGenModal()">Cancel</button>
            <button class="modal-btn save" onclick="generateSessions()">Generate</button>
        </div>
    </div>
</div>

<!-- Holiday Modal -->
<div class="modal-overlay" id="holidayModal">
    <div class="modal-box">
        <h3>Declare Holiday</h3>
        <div class="form-group">
            <label>Date</label>
            <input type="date" id="holidayDate" readonly>
        </div>
        <div class="form-group">
            <label>Batch (leave blank for all batches)</label>
            <select id="holidayBatch">
                <option value="*">All Batches</option>
                <?php foreach($batches as $b): ?>
                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Description</label>
            <input type="text" id="holidayDesc" placeholder="e.g. Diwali, College Day">
        </div>
        <div class="modal-btn-row">
            <button class="modal-btn cancel" onclick="closeHolidayModal()">Cancel</button>
            <button class="modal-btn save" onclick="declareHoliday()">Declare Holiday</button>
        </div>
    </div>
</div>

<!-- Special Class Modal -->
<div class="modal-overlay" id="specialModal">
    <div class="modal-box">
        <h3>Create Special / Extra Class</h3>
        <div class="form-group">
            <label>Date</label>
            <input type="date" id="specialDate" readonly>
        </div>
        <div class="form-group">
            <label>Class Category</label>
            <select id="specialClassType">
                <option value="EXTRA">Extra Class</option>
                <option value="SPECIAL">Special Class</option>
                <option value="REMEDIAL">Remedial Coaching</option>
                <option value="LAB_EXTRA">Lab / Practical Extra</option>
            </select>
        </div>
        <div class="form-group">
            <label>Hour</label>
            <select id="specialHour" onchange="onSpecialHourChange()">
                <optgroup label="Standard Hours">
                    <option value="1">H1 (08:45 – 09:35)</option>
                    <option value="2">H2 (09:35 – 10:25)</option>
                    <option value="3">H3 (10:45 – 11:35)</option>
                    <option value="4">H4 (11:35 – 12:25)</option>
                    <option value="5">H5 (13:25 – 14:15)</option>
                    <option value="6">H6 (14:15 – 15:05)</option>
                    <option value="7">H7 (15:25 – 16:15)</option>
                </optgroup>
                <optgroup label="Extra Hours / Extended Schedule">
                    <option value="8" selected>H8 (16:20 – 17:10) [Extra]</option>
                    <option value="9">H9 (17:10 – 18:00) [Extra]</option>
                    <option value="10">H10 (18:00 – 18:50) [Extra]</option>
                    <option value="11">H11 (18:50 – 19:40) [Extra]</option>
                    <option value="12">H12 (19:40 – 20:30) [Extra]</option>
                </optgroup>
            </select>
        </div>
        <div style="display:flex;gap:12px;">
            <div class="form-group" style="flex:1;">
                <label>Start Time</label>
                <input type="time" id="specialStartTime" value="16:20">
            </div>
            <div class="form-group" style="flex:1;">
                <label>End Time</label>
                <input type="time" id="specialEndTime" value="17:10">
            </div>
        </div>
        <div class="form-group">
            <label>Batch</label>
            <select id="specialBatch">
                <option value="">Select batch...</option>
                <?php foreach($batches as $b): ?>
                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Semester</label>
            <select id="specialSemester">
                <?php for($s=1;$s<=8;$s++): ?>
                <option value="<?= $s ?>">Semester <?= $s ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Subject</label>
            <input type="text" id="specialSubject" placeholder="Subject name">
        </div>
        <div class="form-group">
            <label>Subject Code</label>
            <input type="text" id="specialCode" placeholder="Subject code">
        </div>
        <div class="form-group">
            <label>Faculty</label>
            <input type="text" id="specialFaculty" placeholder="Faculty name">
        </div>
        <div class="modal-btn-row">
            <button class="modal-btn cancel" onclick="closeSpecialModal()">Cancel</button>
            <button class="modal-btn save" onclick="createSpecialClass()">Create Extra Class</button>
        </div>
    </div>
</div>

<!-- Bulk Generate Modal -->
<div class="modal-overlay" id="bulkGenModal">
    <div class="modal-box">
        <h3>⚡ Auto-Generate Sessions from Timetable</h3>
        <p style="color:#666;font-size:13px;line-height:1.5;margin-bottom:16px;">
            This will generate timetable classes across all working days of the selected month.
            <strong>Declared calendar holidays and off-days are strictly skipped</strong> with zero attendance penalties.
        </p>
        <div class="form-group">
            <label>Batch</label>
            <select id="bulkGenBatch">
                <option value="">Select batch...</option>
                <?php foreach($batches as $b): ?>
                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Semester</label>
            <select id="bulkGenSemester">
                <?php for($s=1;$s<=8;$s++): ?>
                <option value="<?= $s ?>">Semester <?= $s ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div style="display:flex;gap:12px;">
            <div class="form-group" style="flex:1;">
                <label>Month</label>
                <select id="bulkGenMonth">
                    <?php for($mIdx=1;$mIdx<=12;$mIdx++): ?>
                    <option value="<?= $mIdx ?>" <?= $mIdx==$month?'selected':'' ?>><?= date('F', mktime(0,0,0,$mIdx,1,2026)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1;">
                <label>Year</label>
                <input type="number" id="bulkGenYear" value="<?= $year ?>">
            </div>
        </div>
        <div class="modal-btn-row">
            <button class="modal-btn cancel" onclick="closeBulkGenModal()">Cancel</button>
            <button class="modal-btn save" onclick="bulkGenerateSessions()">Generate Sessions</button>
        </div>
    </div>
</div>

<!-- Edit / Change Session Modal -->
<div class="modal-overlay" id="editSessionModal">
    <div class="modal-box">
        <h3>Change / Edit Scheduled Class</h3>
        <input type="hidden" id="editSessionId">
        <div class="form-group">
            <label>Subject Name</label>
            <input type="text" id="editSessionSubject">
        </div>
        <div class="form-group">
            <label>Subject Code</label>
            <input type="text" id="editSessionCode">
        </div>
        <div class="form-group">
            <label>Faculty</label>
            <input type="text" id="editSessionFaculty">
        </div>
        <div class="form-group">
            <label>Hour</label>
            <select id="editSessionHour" onchange="onEditHourChange()">
                <?php for($h=1;$h<=12;$h++): ?>
                <option value="<?= $h ?>">H<?= $h ?><?= $h>7 ? ' [Extra]' : '' ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div style="display:flex;gap:12px;">
            <div class="form-group" style="flex:1;">
                <label>Start Time</label>
                <input type="time" id="editSessionStart">
            </div>
            <div class="form-group" style="flex:1;">
                <label>End Time</label>
                <input type="time" id="editSessionEnd">
            </div>
        </div>
        <div class="form-group">
            <label>Session Status</label>
            <select id="editSessionStatus">
                <option value="SCHEDULED">SCHEDULED (Active)</option>
                <option value="CONDUCTED">CONDUCTED</option>
                <option value="SUSPENDED">SUSPENDED</option>
                <option value="CANCELLED">CANCELLED</option>
            </select>
        </div>
        <div class="modal-btn-row">
            <button class="modal-btn cancel" onclick="closeEditSessionModal()">Cancel</button>
            <button class="modal-btn save" onclick="saveSessionEdit()">Save Changes</button>
        </div>
    </div>
</div>

<!-- Suspend Modal -->
<div class="modal-overlay" id="suspendModal">
    <div class="modal-box">
        <h3>Suspend Session</h3>
        <div class="form-group">
            <label>Reason</label>
            <input type="text" id="suspendReason" placeholder="e.g. Faculty on leave, Power outage">
        </div>
        <div class="modal-btn-row">
            <button class="modal-btn cancel" onclick="closeSuspendModal()">Cancel</button>
            <button class="modal-btn save" onclick="suspendSession()">Suspend</button>
        </div>
    </div>
</div>

<!-- Substitution Modal -->
<div class="modal-overlay" id="subModal">
    <div class="modal-box">
        <h3>Substitute Faculty</h3>
        <div class="form-group">
            <label>Substitute Faculty Name</label>
            <input type="text" id="subFaculty" placeholder="Faculty name">
        </div>
        <div class="modal-btn-row">
            <button class="modal-btn cancel" onclick="closeSubModal()">Cancel</button>
            <button class="modal-btn save" onclick="saveSubstitution()">Save</button>
        </div>
    </div>
</div>

<!-- Reschedule Modal -->
<div class="modal-overlay" id="rescheduleModal">
    <div class="modal-box">
        <h3>Reschedule Session</h3>
        <div class="form-group">
            <label>New Date</label>
            <input type="date" id="rescheduleDate">
        </div>
        <div class="form-group">
            <label>New Hour</label>
            <select id="rescheduleHour">
                <?php for($h=1;$h<=7;$h++): ?>
                <option value="<?= $h ?>">H<?= $h ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="modal-btn-row">
            <button class="modal-btn cancel" onclick="closeRescheduleModal()">Cancel</button>
            <button class="modal-btn save" onclick="rescheduleSession()">Reschedule</button>
        </div>
    </div>
</div>

<script>
let selectedDate = '';
let suspendSessionId = '';
let subSessionId = '';
let rescheduleSessionId = '';

const STANDARD_HOUR_TIMES = {
    1: {start: '08:45', end: '09:35'},
    2: {start: '09:35', end: '10:25'},
    3: {start: '10:45', end: '11:35'},
    4: {start: '11:35', end: '12:25'},
    5: {start: '13:25', end: '14:15'},
    6: {start: '14:15', end: '15:05'},
    7: {start: '15:25', end: '16:15'},
    8: {start: '16:20', end: '17:10'},
    9: {start: '17:10', end: '18:00'},
    10: {start: '18:00', end: '18:50'},
    11: {start: '18:50', end: '19:40'},
    12: {start: '19:40', end: '20:30'},
};

function escapeHtml(text) { const div=document.createElement('div'); div.textContent=text; return div.innerHTML; }
function escapeAttr(text) { return String(text||'').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }

document.addEventListener('DOMContentLoaded', function() {
    loadMonthCalendar();
});

function loadMonthCalendar() {
    fetch(`attendance_api.php?action=mentor_month_calendar&month=<?= $month ?>&year=<?= $year ?>`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.days) {
                for (let d = 1; d <= <?= $daysInMonth ?>; d++) {
                    const cell = document.getElementById('cal-cell-' + d);
                    const countEl = document.getElementById('cal-' + d);
                    const dayData = res.days[d];
                    if (!cell || !dayData) continue;

                    cell.classList.remove('status-marked', 'status-partial', 'status-scheduled', 'status-holiday', 'status-suspended');
                    if (dayData.status && dayData.status !== 'none') {
                        cell.classList.add('status-' + dayData.status);
                    }
                    if (countEl) {
                        countEl.textContent = dayData.label || '';
                    }
                }
            }
        })
        .catch(err => console.error('Month calendar error:', err));
}

function showToast(message, type='info') {
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;top:80px;right:20px;background:${type==='success'?'#28a745':type==='error'?'#dc3545':'#17a2b8'};color:#fff;padding:14px 24px;border-radius:12px;font-size:14px;font-weight:600;z-index:10000;box-shadow:0 8px 30px rgba(0,0,0,0.2);animation:toastSlideIn 0.3s ease;max-width:350px;`;
    toast.textContent = message;
    document.body.appendChild(toast);
    if (!document.getElementById('toastStyles')) {
        const s = document.createElement('style'); s.id = 'toastStyles';
        s.textContent = '@keyframes toastSlideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}@keyframes toastSlideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(100%);opacity:0}}';
        document.head.appendChild(s);
    }
    setTimeout(() => { toast.style.animation = 'toastSlideOut 0.3s ease'; setTimeout(()=>toast.remove(),300); }, 3000);
}

function showDateDetail(dateStr) {
    selectedDate = dateStr;
    fetch(`attendance_api.php?action=sessions_for_date&date=${dateStr}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') { showToast('Failed to load', 'error'); return; }
            document.getElementById('dateModalTitle').textContent = 'Sessions for ' + dateStr;

            let html = '';
            if (data.holiday) {
                html += `<div style="background:#fee2e2;border:1px solid #fecaca;padding:16px;border-radius:12px;margin-bottom:16px;text-align:center;">
                    <div style="font-size:24px;">🏖️</div>
                    <div style="font-weight:700;color:#991b1b;margin-top:6px;font-size:15px;">Holiday: ${escapeHtml(data.holiday.description)}</div>
                    <div style="font-size:12px;color:#b91c1c;margin-top:4px;">Holidays strictly incur 0% negative penalty on attendance.</div>
                    <button class="action-btn" style="margin-top:10px;background:#dc2626;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:12px;font-weight:700;" onclick="removeHoliday('${data.holiday._id}')">Remove Holiday / Convert to Working Day</button>
                </div>`;
            }

            html += '<div class="actions-row">';
            html += `<button class="action-btn gen" onclick="openGenModal()">Generate Sessions</button>`;
            html += `<button class="action-btn holiday" onclick="openHolidayModal()">Declare Holiday</button>`;
            html += `<button class="action-btn special" onclick="openSpecialModal()">+ Extra / Special Class</button>`;
            html += '</div>';

            if (data.sessions.length === 0) {
                html += '<div style="text-align:center;padding:30px;color:#888;margin-top:16px;">No sessions for this date. Generate sessions from the timetable or add an extra class.</div>';
            } else {
                html += '<div class="session-list">';
                data.sessions.forEach(s => {
                    const isClosed = (s.semester_status === 'CLOSED');
                    const isLocked = (s.semester_status === 'LOCKED');
                    const isExtraHour = parseInt(s.hour) > 7;

                    let actionBtns = '';
                    if (isClosed) {
                        actionBtns = `<a href="mentor_attendance_mark.php?session_id=${s.attendance_session_id}" class="sess-btn mark" style="background:#6c757d;">View Attendance (Closed)</a>`;
                    } else if (isLocked) {
                        actionBtns = `<a href="mentor_attendance_mark.php?session_id=${s.attendance_session_id}" class="sess-btn mark" style="background:#ffc107;color:#333;">Locked</a>`;
                    } else {
                        if (s.status === 'SCHEDULED') actionBtns += `<a href="mentor_attendance_mark.php?session_id=${s.attendance_session_id}" class="sess-btn mark">Mark</a>`;
                        if (s.status === 'CONDUCTED') actionBtns += `<a href="mentor_attendance_mark.php?session_id=${s.attendance_session_id}" class="sess-btn mark" style="background:#17a2b8;">Edit Attendance</a>`;
                        if (s.status === 'SUSPENDED') actionBtns += `<a href="mentor_attendance_mark.php?session_id=${s.attendance_session_id}" class="sess-btn mark" style="background:#6c757d;">View / Unsuspend</a>`;
                        if (s.status === 'SCHEDULED') {
                            actionBtns += ` <button class="sess-btn suspend" onclick="openSuspendModal('${s.attendance_session_id}')">Suspend</button>`;
                            actionBtns += ` <button class="sess-btn substitute" onclick="openSubModal('${s.attendance_session_id}')">Substitute</button>`;
                            actionBtns += ` <button class="sess-btn reschedule" onclick="openRescheduleModal('${s.attendance_session_id}')">Reschedule</button>`;
                        }
                    }

                    // Always allow changing / editing scheduled, cancelled, or suspended sessions
                    actionBtns += ` <button class="sess-btn" style="background:#4f46e5;color:#fff;" onclick="openEditSessionModal('${s.attendance_session_id}', '${escapeAttr(s.subject||'')}', '${escapeAttr(s.subject_code||'')}', '${escapeAttr(s.actual_faculty||s.original_faculty||'')}', ${s.hour}, '${s.start_time||''}', '${s.end_time||''}', '${s.status}')">Edit / Change</button>`;

                    html += `<div class="session-item" style="${isExtraHour ? 'border-left:4px solid #f59e0b;' : ''}">
                        <div class="session-info">
                            <div class="s-hour">
                                H${s.hour} ${s.start_time}-${s.end_time}
                                ${isExtraHour ? '<span style="font-size:10px;background:#f59e0b;color:#fff;padding:2px 6px;border-radius:4px;margin-left:6px;font-weight:700;">EXTRA HOUR</span>' : ''}
                                ${s.class_type && s.class_type !== 'REGULAR' ? `<span style="font-size:10px;background:#8b5cf6;color:#fff;padding:2px 6px;border-radius:4px;margin-left:4px;font-weight:700;">${escapeHtml(s.class_type)}</span>` : ''}
                            </div>
                            <div class="s-subject">${escapeHtml(s.subject)} ${s.subject_code ? '(' + escapeHtml(s.subject_code) + ')' : ''}</div>
                            <div class="s-meta">Faculty: ${escapeHtml(s.actual_faculty || s.original_faculty)} · Batch: ${escapeHtml(s.batch)} · Sem: ${s.semester}</div>
                            ${isClosed ? '<div style="color:#6c757d;font-size:12px;margin-top:3px;font-weight:600;">🔒 Semester attendance closed (Read-only)</div>' : ''}
                            ${isLocked ? `<div style="color:#856404;font-size:12px;margin-top:3px;font-weight:600;">🔒 Attendance Locked: ${escapeHtml(s.lock_reason || 'Previous semester not closed')}</div>` : ''}
                            ${s.cancellation_reason ? `<div style="color:#dc2626;font-size:12px;margin-top:3px;font-weight:600;">Cancelled: ${escapeHtml(s.cancellation_reason)}</div>` : ''}
                            ${s.suspension_reason ? `<div style="color:#d9534f;font-size:12px;margin-top:3px;">Reason: ${escapeHtml(s.suspension_reason)}</div>` : ''}
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                            <div style="display:flex;gap:6px;align-items:center;">
                                ${isClosed ? '<span class="sess-badge" style="background:#e2e3e5;color:#383d41;">CLOSED</span>' : ''}
                                ${isLocked ? '<span class="sess-badge" style="background:#fff3cd;color:#856404;">LOCKED</span>' : ''}
                                <span class="sess-badge ${s.status}">${s.status}</span>
                            </div>
                            <div class="session-actions" style="flex-wrap:wrap;justify-content:flex-end;">
                                ${actionBtns}
                            </div>
                        </div>
                    </div>`;
                });
                html += '</div>';
            }

            document.getElementById('dateModalBody').innerHTML = html;
            document.getElementById('dateModal').classList.add('active');
        });
}

function closeDateModal() { document.getElementById('dateModal').classList.remove('active'); }

function openGenModal() { closeDateModal(); document.getElementById('genDate').value = selectedDate; document.getElementById('genModal').classList.add('active'); }
function closeGenModal() { document.getElementById('genModal').classList.remove('active'); }
function generateSessions() {
    const batch = document.getElementById('genBatch').value;
    const semester = document.getElementById('genSemester').value;
    if (!batch) { showToast('Select a batch', 'error'); return; }
    const formData = new FormData();
    formData.append('action', 'generate_sessions');
    formData.append('date', document.getElementById('genDate').value);
    formData.append('batch', batch);
    formData.append('semester', semester);
    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                closeGenModal();
                loadMonthCalendar();
                if (selectedDate) showDateDetail(selectedDate);
            } else {
                showToast(data.message || 'Failed', 'error');
            }
        });
}

function openBulkGenModal() { document.getElementById('bulkGenModal').classList.add('active'); }
function closeBulkGenModal() { document.getElementById('bulkGenModal').classList.remove('active'); }
function bulkGenerateSessions() {
    const batch = document.getElementById('bulkGenBatch').value;
    const semester = document.getElementById('bulkGenSemester').value;
    const m = document.getElementById('bulkGenMonth').value;
    const y = document.getElementById('bulkGenYear').value;
    if (!batch) { showToast('Select a batch', 'error'); return; }

    const formData = new FormData();
    formData.append('action', 'generate_timetable_sessions');
    formData.append('batch', batch);
    formData.append('semester', semester);
    formData.append('month', m);
    formData.append('year', y);

    showToast('Generating sessions across working days...', 'info');
    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                closeBulkGenModal();
                loadMonthCalendar();
            } else {
                showToast(data.message || 'Failed', 'error');
            }
        });
}

function openHolidayModal() { closeDateModal(); document.getElementById('holidayDate').value = selectedDate; document.getElementById('holidayModal').classList.add('active'); }
function closeHolidayModal() { document.getElementById('holidayModal').classList.remove('active'); }
function declareHoliday() {
    const desc = document.getElementById('holidayDesc').value.trim();
    if (!desc) { showToast('Description required', 'error'); return; }
    const formData = new FormData();
    formData.append('action', 'declare_holiday');
    formData.append('date', document.getElementById('holidayDate').value);
    formData.append('description', desc);
    formData.append('batch', document.getElementById('holidayBatch').value);
    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                closeHolidayModal();
                loadMonthCalendar();
                if (selectedDate) showDateDetail(selectedDate);
            } else {
                showToast(data.message || 'Failed', 'error');
            }
        });
}

function removeHoliday(id) {
    if (!confirm('Are you sure you want to remove this holiday and convert this date back to a normal working day? Scheduled classes will be restored.')) return;
    const formData = new FormData();
    formData.append('action', 'remove_holiday');
    formData.append('holiday_id', id);
    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                closeDateModal();
                loadMonthCalendar();
                if (selectedDate) showDateDetail(selectedDate);
            } else {
                showToast(data.message || 'Failed', 'error');
            }
        });
}

function onSpecialHourChange() {
    const h = parseInt(document.getElementById('specialHour').value);
    if (STANDARD_HOUR_TIMES[h]) {
        document.getElementById('specialStartTime').value = STANDARD_HOUR_TIMES[h].start;
        document.getElementById('specialEndTime').value = STANDARD_HOUR_TIMES[h].end;
    }
}

function openSpecialModal() {
    closeDateModal();
    document.getElementById('specialDate').value = selectedDate;
    onSpecialHourChange();
    document.getElementById('specialModal').classList.add('active');
}
function closeSpecialModal() { document.getElementById('specialModal').classList.remove('active'); }
function createSpecialClass() {
    const batch = document.getElementById('specialBatch').value;
    const subject = document.getElementById('specialSubject').value.trim();
    if (!batch || !subject) { showToast('Batch and subject required', 'error'); return; }

    const formData = new FormData();
    formData.append('action', 'create_special_class');
    formData.append('date', document.getElementById('specialDate').value);
    formData.append('hour', document.getElementById('specialHour').value);
    formData.append('start_time', document.getElementById('specialStartTime').value);
    formData.append('end_time', document.getElementById('specialEndTime').value);
    formData.append('class_type', document.getElementById('specialClassType').value);
    formData.append('subject', subject);
    formData.append('subject_code', document.getElementById('specialCode').value);
    formData.append('faculty', document.getElementById('specialFaculty').value);
    formData.append('batch', batch);
    formData.append('semester', document.getElementById('specialSemester').value);

    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                closeSpecialModal();
                loadMonthCalendar();
                if (selectedDate) showDateDetail(selectedDate);
            } else {
                showToast(data.message || 'Failed', 'error');
            }
        });
}

function onEditHourChange() {
    const h = parseInt(document.getElementById('editSessionHour').value);
    if (STANDARD_HOUR_TIMES[h]) {
        document.getElementById('editSessionStart').value = STANDARD_HOUR_TIMES[h].start;
        document.getElementById('editSessionEnd').value = STANDARD_HOUR_TIMES[h].end;
    }
}

function openEditSessionModal(id, subject, code, faculty, hour, start, end, status) {
    document.getElementById('editSessionId').value = id;
    document.getElementById('editSessionSubject').value = subject;
    document.getElementById('editSessionCode').value = code;
    document.getElementById('editSessionFaculty').value = faculty;
    document.getElementById('editSessionHour').value = hour;
    document.getElementById('editSessionStart').value = start || (STANDARD_HOUR_TIMES[hour] ? STANDARD_HOUR_TIMES[hour].start : '');
    document.getElementById('editSessionEnd').value = end || (STANDARD_HOUR_TIMES[hour] ? STANDARD_HOUR_TIMES[hour].end : '');
    document.getElementById('editSessionStatus').value = status;
    document.getElementById('editSessionModal').classList.add('active');
}

function closeEditSessionModal() { document.getElementById('editSessionModal').classList.remove('active'); }

function saveSessionEdit() {
    const id = document.getElementById('editSessionId').value;
    const subject = document.getElementById('editSessionSubject').value.trim();
    const code = document.getElementById('editSessionCode').value.trim();
    const faculty = document.getElementById('editSessionFaculty').value.trim();
    const hour = document.getElementById('editSessionHour').value;
    const start = document.getElementById('editSessionStart').value;
    const end = document.getElementById('editSessionEnd').value;
    const status = document.getElementById('editSessionStatus').value;

    if (!id || !subject) { showToast('Subject is required', 'error'); return; }

    const formData = new FormData();
    formData.append('action', 'update_session');
    formData.append('session_id', id);
    formData.append('subject', subject);
    formData.append('subject_code', code);
    formData.append('faculty', faculty);
    formData.append('hour', hour);
    formData.append('start_time', start);
    formData.append('end_time', end);
    formData.append('status', status);

    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                closeEditSessionModal();
                loadMonthCalendar();
                if (selectedDate) showDateDetail(selectedDate);
            } else {
                showToast(data.message || 'Update failed', 'error');
            }
        });
}

function openSuspendModal(id) { suspendSessionId = id; document.getElementById('suspendReason').value = ''; document.getElementById('suspendModal').classList.add('active'); }
function closeSuspendModal() { document.getElementById('suspendModal').classList.remove('active'); }
function suspendSession() {
    const reason = document.getElementById('suspendReason').value.trim();
    if (!reason) { showToast('Reason required', 'error'); return; }
    const formData = new FormData();
    formData.append('action', 'suspend_session');
    formData.append('session_id', suspendSessionId);
    formData.append('reason', reason);
    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                closeSuspendModal();
                loadMonthCalendar();
                if (selectedDate) showDateDetail(selectedDate);
            } else {
                showToast(data.message || 'Failed', 'error');
            }
        });
}

function openSubModal(id) { subSessionId = id; document.getElementById('subFaculty').value = ''; document.getElementById('subModal').classList.add('active'); }
function closeSubModal() { document.getElementById('subModal').classList.remove('active'); }
function saveSubstitution() {
    const faculty = document.getElementById('subFaculty').value.trim();
    if (!faculty) { showToast('Faculty name required', 'error'); return; }
    const formData = new FormData();
    formData.append('action', 'substitute');
    formData.append('session_id', subSessionId);
    formData.append('substitute_faculty', faculty);
    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                closeSubModal();
                loadMonthCalendar();
                if (selectedDate) showDateDetail(selectedDate);
            } else {
                showToast(data.message || 'Failed', 'error');
            }
        });
}

function openRescheduleModal(id) { rescheduleSessionId = id; document.getElementById('rescheduleDate').value = ''; document.getElementById('rescheduleModal').classList.add('active'); }
function closeRescheduleModal() { document.getElementById('rescheduleModal').classList.remove('active'); }
function rescheduleSession() {
    const date = document.getElementById('rescheduleDate').value;
    const hour = document.getElementById('rescheduleHour').value;
    if (!date) { showToast('New date required', 'error'); return; }
    const formData = new FormData();
    formData.append('action', 'reschedule_session');
    formData.append('session_id', rescheduleSessionId);
    formData.append('new_date', date);
    formData.append('new_hour', hour);
    fetch('attendance_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                closeRescheduleModal();
                loadMonthCalendar();
                if (selectedDate) showDateDetail(selectedDate);
            } else {
                showToast(data.message || 'Failed', 'error');
            }
        });
}

document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', function(e) { if (e.target === m) m.classList.remove('active'); }));

function toggleNotif() { const d=document.getElementById('notifDrop'); d.classList.toggle('open'); if(d.classList.contains('open')) loadNotifs(); }
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
