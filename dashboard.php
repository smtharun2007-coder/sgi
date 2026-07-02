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
    <title>SGI – Dashboard</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<nav class="navbar">
<a href="dashboard.php" class="nav-brand">
    <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo"> SGI
</a>
    <div class="nav-links">
        <a href="dashboard.php">Home</a>
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
    <div class="profile-card">
        <div class="profile-info">
            <h2><?= htmlspecialchars($u['name']) ?></h2>
            <p class="profile-roll"><?= htmlspecialchars($u['roll']) ?></p>
            <p><strong>Integrated No:</strong> <?= htmlspecialchars($u['reg']) ?></p>
            <p><strong>DOB:</strong> <?= htmlspecialchars($u['dob']) ?></p>
            <p><strong>Father:</strong> <?= htmlspecialchars($u['father']) ?></p>
            <p><strong>Mother:</strong> <?= htmlspecialchars($u['mother']) ?></p>
            <p><strong>Department:</strong> <?= htmlspecialchars($u['dept']) ?></p>
            <?php if (!empty($u['batch_no'])): ?>
            <p><strong>Batch No:</strong> <?= htmlspecialchars($u['batch_no']) ?></p>
            <?php endif; ?>
            <p><strong>Class:</strong> <?= htmlspecialchars($u['class']) ?></p>
            <p><strong>Year:</strong> <?= $u['year_from'] ?> – <?= $u['year_to'] ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($u['email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($u['phone']) ?></p>
            <?php
// Fetch mentor details for the popup
$mentorData = null;
if (!empty($u['mentor_id'])) {
    $mentorData = $mentors->findOne(['mentor_id' => $u['mentor_id']]);
}
?>
            <?php if (!empty($u['mentor_id']) && $mentorData): ?>
            <div style="margin-top:14px;background:linear-gradient(135deg,#1a1a2e,#e94560);border-radius:12px;padding:20px 28px;display:inline-block;box-shadow:0 4px 14px rgba(233,69,96,0.3);cursor:pointer;transition:transform 0.2s,box-shadow 0.2s;" onclick="showMentorModal()" onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(233,69,96,0.4)';" onmouseleave="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 14px rgba(233,69,96,0.3)';">
                <div style="font-size:13px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Mentor ID</div>
                <div style="font-size:28px;font-weight:700;color:#fff;"><?= htmlspecialchars($u['mentor_id']) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="profile-photo">
            <?php if (!empty($u['photo'])): ?>
                <img src="<?= htmlspecialchars(imgUrl($u['photo'])) ?>" alt="Photo">
            <?php else: ?>
                <div class="no-photo">No Photo</div>
            <?php endif; ?>
            <?php if (!empty($u['signature'])): ?>
                <div class="signature-box" style="margin-top:30px;">
                    <img src="<?= htmlspecialchars(imgUrl($u['signature'])) ?>" alt="Signature">
                    <label>Student Signature</label>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FEATURE CARDS -->
    <div class="sem-cards" style="margin-top:20px;">
        <div class="sem-card">
            <div class="sem-card-header" style="background:linear-gradient(135deg,#1a1a2e,#e94560);"><h3>&#127979; Academics</h3></div>
            <div class="sem-card-body" style="text-align:center;padding:24px 20px;">
                <p style="font-size:13px;color:#888;margin-bottom:16px;">View GPA, SGI, semester cards, performance chart &amp; analytics.</p>
                <a href="academics.php" class="btn-primary" style="width:auto;padding:10px 24px;">Open Academics</a>
            </div>
        </div>
        <div class="sem-card">
            <div class="sem-card-header" style="background:linear-gradient(135deg,#1a1a2e,#2980b9);"><h3>&#128197; Calendar</h3></div>
            <div class="sem-card-body" style="text-align:center;padding:24px 20px;">
                <p style="font-size:13px;color:#888;margin-bottom:16px;">View exam dates, holidays &amp; events from your mentor.</p>
                <a href="calendar.php" class="btn-primary" style="width:auto;padding:10px 24px;">Open Calendar</a>
            </div>
        </div>
        <div class="sem-card">
            <div class="sem-card-header" style="background:linear-gradient(135deg,#1a1a2e,#27ae60);"><h3>&#128226; Announcements</h3></div>
            <div class="sem-card-body" style="text-align:center;padding:24px 20px;">
                <p style="font-size:13px;color:#888;margin-bottom:16px;">View announcements from your mentor.</p>
                <a href="announcements.php" class="btn-primary" style="width:auto;padding:10px 24px;">Open Announcements</a>
            </div>
        </div>
    </div>
</div>
<script>
function toggleNotif() {
    const drop = document.getElementById('notifDrop');
    drop.classList.toggle('open');
    if (drop.classList.contains('open')) loadNotifs();
}
function loadNotifs() {
    fetch('notifications.php?fetch=1')
        .then(r=>r.json()).then(data=>{
            const list = document.getElementById('notifList');
            if (!data.length) { list.innerHTML='<div class="notif-empty">No notifications</div>'; return; }
            list.innerHTML = data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}</div><div class="notif-time">${n.time}</div></div>`).join('');
        });
}
function markAll(e) {
    e.preventDefault();
    fetch('notifications.php?mark_all=1');
    document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread'));
    const badge = document.querySelector('.notif-badge');
    if(badge) badge.remove();
}
function clearAll(e) {
    e.preventDefault();
    fetch('notifications.php?delete_all=1');
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

<!-- Mentor Details Modal -->
<?php if ($mentorData): ?>
<div id="mentorModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;align-items:center;justify-content:center;" onclick="if(event.target===this)closeMentorModal()">
    <div style="background:#fff;border-radius:16px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);overflow:hidden;">
        <div style="background:linear-gradient(135deg,#1a1a2e,#e94560);padding:24px;text-align:center;position:relative;">
            <button onclick="closeMentorModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;color:#fff;font-size:24px;cursor:pointer;">&times;</button>
            <?php if (!empty($mentorData['photo'])): ?>
            <img src="<?= htmlspecialchars(imgUrl($mentorData['photo'])) ?>" alt="Mentor Photo" style="width:100px;height:100px;border-radius:50%;border:4px solid #fff;object-fit:cover;margin-bottom:12px;">
            <?php else: ?>
            <div style="width:100px;height:100px;border-radius:50%;border:4px solid #fff;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:40px;color:#fff;">👤</div>
            <?php endif; ?>
            <h2 style="color:#fff;margin:0;font-size:22px;"><?= htmlspecialchars($mentorData['name']) ?></h2>
            <p style="color:rgba(255,255,255,0.7);margin:4px 0 0;font-size:14px;">Mentor ID: <?= htmlspecialchars($mentorData['mentor_id']) ?></p>
        </div>
        <div style="padding:24px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;padding:12px;background:#f8f9fa;border-radius:10px;">
                <span style="font-size:20px;">📧</span>
                <div>
                    <div style="font-size:11px;color:#888;text-transform:uppercase;">Email</div>
                    <div style="font-size:14px;color:#333;"><?= htmlspecialchars($mentorData['email'] ?? 'N/A') ?></div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;padding:12px;background:#f8f9fa;border-radius:10px;">
                <span style="font-size:20px;">📱</span>
                <div>
                    <div style="font-size:11px;color:#888;text-transform:uppercase;">Phone</div>
                    <div style="font-size:14px;color:#333;"><?= htmlspecialchars($mentorData['phone'] ?? 'N/A') ?></div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:12px;background:#f8f9fa;border-radius:10px;">
                <span style="font-size:20px;">🏛️</span>
                <div>
                    <div style="font-size:11px;color:#888;text-transform:uppercase;">Department</div>
                    <div style="font-size:14px;color:#333;"><?= htmlspecialchars($mentorData['dept'] ?? 'N/A') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function showMentorModal() {
    document.getElementById('mentorModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeMentorModal() {
    document.getElementById('mentorModal').style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMentorModal();
});
</script>
<?php endif; ?>

<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>

