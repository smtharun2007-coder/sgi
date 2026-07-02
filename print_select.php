<?php
include 'config.php';
requireLogin();
$u = $_SESSION['user'];
$unreadCount = $notifications->countDocuments(['roll'=>$u['roll'],'read'=>false]);
$semCursor = $semesters->find(['roll' => $u['roll']], ['sort' => ['sem' => 1]]);
$semList   = iterator_to_array($semCursor);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI - Print</title>
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
                <div id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
<div class="form-box" style="max-width:600px;text-align:center;">
    <h2>Print Report</h2>
    <hr style="margin:16px 0;">

    <!-- OVERALL REPORT -->
    <div class="print-option-card">
        <div class="print-option-info">
            <h3>Overall Report</h3>
            <p>All semesters summary.</p>
        </div>
        <a href="print_dashboard.php" target="_blank" class="btn-primary" style="width:auto;padding:12px 30px;margin-top:0;font-size:15px;">Print</a>
    </div>

    <!-- SUBJECT REPORT -->
    <div class="print-option-card">
        <div class="print-option-info">
            <h3>All Subject Report</h3>
            <p>Best/worst semester, subject averages &amp; CA marks.</p>
        </div>
        <a href="print_subjects.php" target="_blank" class="btn-primary" style="width:auto;padding:12px 30px;margin-top:0;font-size:15px;">Print</a>
    </div>

    <hr style="margin:20px 0;">

    <!-- PER SEMESTER -->
    <h3 style="color:#1a1a2e;margin-bottom:12px;">Print by Semester</h3>
    <?php if (empty($semList)): ?>
        <p class="no-data">No semesters added yet.</p>
    <?php else: ?>
    <div class="print-sem-list">
        <?php foreach ($semList as $s): ?>
        <div class="print-option-card">
            <div class="print-option-info">
                <h3>Semester <?= $s['sem'] ?></h3>
            </div>
            <?php if (!empty($s['sgi'])): ?>
                <a href="print_report.php?id=<?= (string)$s['_id'] ?>" target="_blank" class="btn-calc" style="margin-top:0;padding:12px 30px;font-size:15px;">Print</a>
            <?php else: ?>
                <span class="btn-calc btn-disabled" style="margin-top:0;padding:12px 30px;font-size:15px;">SGI Pending</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
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

