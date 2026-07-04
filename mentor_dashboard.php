<?php
include 'config.php';
if (!isset($_SESSION['mentor'])) { header("Location: mentor_login.php"); exit; }

// Mentor session timeout - 30 minutes
if (isset($_SESSION['last_mentor_activity']) && (time() - $_SESSION['last_mentor_activity'] > 1800)) {
    unset($_SESSION['mentor']);
    header("Location: mentor_login.php?timeout=1");
    exit;
}
$_SESSION['last_mentor_activity'] = time();

$m = $_SESSION['mentor'];
$unreadCount = $notifications->countDocuments(['mentor_id'=>$m['mentor_id'],'read'=>false]);

// Fetch all students linked to this mentor
$studentCursor = $users->find(['mentor_id' => $m['mentor_id']]);
$studentList   = iterator_to_array($studentCursor);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Mentor Dashboard</title>
    <link rel="stylesheet" href="/css/style.css?v=2">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .mentor-badge {
            display: inline-block;
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            color: #fff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .mentor-navbar { background: linear-gradient(135deg, #1a1a2e, #8e44ad); }
        .student-list-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            margin-top: 24px;
        }
        .student-list-header {
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            padding: 16px 24px;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
        }
        .student-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            border-bottom: 1px solid #f0f2f5;
        }
        .student-row:last-child { border-bottom: none; }
        .student-row:hover { background: #fafafa; }
        .student-avatar {
            width: 44px; height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #8e44ad;
            margin-right: 14px;
        }
        .student-avatar-placeholder {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 16px;
            margin-right: 14px; flex-shrink: 0;
        }
        .student-info { flex: 1; }
        .student-info .s-name { font-size: 15px; font-weight: 700; color: #1a1a2e; }
        .student-info .s-roll { font-size: 13px; color: #888; margin-top: 2px; }
        .student-info .s-dept { font-size: 12px; color: #aaa; }
    </style>
</head>
<body>
<nav class="navbar" style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);">
    <a href="mentor_dashboard.php" class="nav-brand">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo"> SGI <span style="font-size:13px;opacity:0.7;font-weight:400;">Mentor</span>
    </a>
    <div class="nav-links">
        <a href="mentor_dashboard.php">Home</a>
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
                <div class="notif-list-scroll" id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <a href="mentor_logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="container">

    <!-- MENTOR PROFILE CARD -->
    <div class="profile-card">
        <div class="profile-info">
            <h2><?= htmlspecialchars($m['name']) ?></h2>
            <p class="profile-roll"><?= htmlspecialchars($m['mentor_id']) ?></p>
            <p><strong>Department:</strong> <?= htmlspecialchars($m['dept']) ?></p>
            <?php if (!empty($m['batch_no'])): ?>
            <p><strong>Batch No:</strong> <?= htmlspecialchars($m['batch_no']) ?></p>
            <?php endif; ?>
            <p><strong>Email:</strong> <?= htmlspecialchars($m['email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($m['phone']) ?></p>
            <div style="margin-top:14px;"><span class="mentor-badge">MENTOR</span></div>
        </div>
        <div class="profile-photo">
            <?php if (!empty($m['photo'])): ?>
                <img src="<?= htmlspecialchars(imgUrl($m['photo'])) ?>" alt="Photo">
            <?php else: ?>
                <div class="no-photo">No Photo</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SUMMARY -->
    <div class="summary-row">
        <div class="summary-card" style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);">
            <h3>My Students</h3>
            <p><?= count($studentList) ?></p>
        </div>
    </div>

    <!-- STUDENTS LIST -->
    <div class="student-list-card">
        <div class="student-list-header">👥 Assigned Students</div>
        <?php if (empty($studentList)): ?>
            <p class="no-data" style="padding:24px;">No students linked to your Mentor ID yet.</p>
        <?php else: ?>
<?php foreach ($studentList as $st): ?>
            <div class="student-row" style="cursor:pointer;" onclick="showStudentSemesters('<?= htmlspecialchars($st['roll']) ?>')">
                <div style="display:flex;align-items:center;">
                    <?php if (!empty($st['photo'])): ?>
                        <img src="<?= htmlspecialchars(imgUrl($st['photo'])) ?>" class="student-avatar" alt="">
                    <?php else: ?>
                        <div class="student-avatar-placeholder"><?= strtoupper(substr($st['name'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <div class="student-info">
                        <div class="s-name"><?= htmlspecialchars($st['name']) ?></div>
                        <div class="s-roll"><?= htmlspecialchars($st['roll']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($st['reg']) ?></div>
                        <div class="s-dept"><?= htmlspecialchars($st['dept']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($st['class']) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
<script>
async function showStudentSemesters(roll) {
    try {
        const res = await fetch('mentor_student_semesters.php?roll=' + encodeURIComponent(roll));
        const data = await res.json();
        if (!data || data.status !== 'success') {
            alert(data?.message || 'Failed to load student semesters');
            return;
        }

        // Remove old modal if exists
        const existing = document.getElementById('studentSemModal');
        if (existing) existing.remove();

        const semesters = data.semesters || [];
        const rows = semesters.map(s => {
            const sgi = (s.sgi === null || s.sgi === undefined) ? '—' : s.sgi;
            const grade = (function(g){
                if (g === null || g === undefined) return '—';
                if (g >= 9) return 'O (Excellent)';
                if (g >= 8) return 'A (Very Good)';
                if (g >= 7) return 'B (Good)';
                if (g >= 6) return 'C (Average)';
                return 'D (Needs Improvement)';
            })(s.sgi);

            return `
                <tr>
                    <td>${s.sem ?? '—'}</td>
                    <td>${sgi}</td>
                    <td>${grade}</td>
                    <td>${s.attendance ?? '—'}%</td>
                    <td>${s.gpa ?? '—'}</td>
                    <td>
                        ${s.result_photo ? `<a href="#" onclick="event.preventDefault();window.open('${s.result_photo}','_blank');">View Result</a>` : '—'}
                    </td>
                </tr>
            `;
        }).join('');

        const modal = document.createElement('div');
        modal.id = 'studentSemModal';
        modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:3000;display:flex;align-items:center;justify-content:center;padding:20px;';
        modal.innerHTML = `
            <div style="background:#fff;border-radius:16px;max-width:1000px;width:95%;max-height:85vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,0.35);">
                <div style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);color:#fff;padding:18px 22px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-weight:800;font-size:18px;">Student: ${data.student?.name || ''}</div>
                        <div style="opacity:0.85;font-size:13px;">Roll: ${data.student?.roll || roll}</div>
                    </div>
                    <button onclick="document.getElementById('studentSemModal').remove();" style="background:none;border:none;color:#fff;font-size:26px;cursor:pointer;">×</button>
                </div>
                <div style="padding:18px 22px;">
                    ${semesters.length ? `
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="text-align:left;border-bottom:2px solid #eee;">
                                <th style="padding:10px 8px;">Semester</th>
                                <th style="padding:10px 8px;">SGI</th>
                                <th style="padding:10px 8px;">Grade</th>
                                <th style="padding:10px 8px;">Attendance</th>
                                <th style="padding:10px 8px;">GPA</th>
                                <th style="padding:10px 8px;">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows}
                        </tbody>
                    </table>` : `<div style="color:#777;text-align:center;padding:40px 10px;">No SGI calculated semesters found.</div>`}
                </div>
            </div>
        `;
        modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
        document.body.appendChild(modal);
    } catch (e) {
        alert('Error: ' + (e?.message || e));
    }
}

function toggleNotif() {
    const drop = document.getElementById('notifDrop');
    drop.classList.toggle('open');
    if (drop.classList.contains('open')) loadNotifs();
}

function loadNotifs() {
    fetch('notifications.php?fetch=1&mentor=1')
        .then(r=>r.json()).then(data=>{
            const list = document.getElementById('notifList');
            if (!data.length) { list.innerHTML='<div class="notif-empty">No notifications</div>'; return; }
            list.innerHTML = data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}</div><div class="notif-time">${n.time}</div></div>`).join('');
        });
}
function markAll(e) {
    e.preventDefault();
    fetch('notifications.php?mark_all=1&mentor=1');
    document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread'));
    const badge = document.querySelector('.notif-badge');
    if(badge) badge.remove();
}
function clearAll(e) {
    e.preventDefault();
    fetch('notifications.php?delete_all=1&mentor=1');
    document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>';
    const badge = document.querySelector('.notif-badge');
    if(badge) badge.remove();
}
document.addEventListener('click', e => {
    const btn = document.getElementById('bellBtn');
    const drop = document.getElementById('notifDrop');
    if (btn && drop && !btn.contains(e.target) && !drop.contains(e.target))
        drop.classList.remove('open');
});
</script>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>
