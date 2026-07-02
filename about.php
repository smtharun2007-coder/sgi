<?php
include 'config.php'; requireLogin();
$u = $_SESSION['user'];
$unreadCount = $notifications->countDocuments(['roll'=>$u['roll'],'read'=>false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – About</title>
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
                <div class="notif-dropdown-header">Notifications <a href="#" onclick="markAll(event)">Mark all read</a></div>
                <div id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">

    <!-- ABOUT SGI -->
    <div class="section">
        <h2>About SGI</h2>
        <p>The <strong>Student Growth Index (SGI)</strong> is a comprehensive scoring system designed to evaluate a student's overall development beyond just academics.</p>
        <br>
        <ul class="about-list">
            <li><strong>Academic (40%)</strong> – CAT scores, GPA, and CGPA performance.</li>
            <li><strong>Skills (20%)</strong> – Credit courses, coding platforms, and normal courses.</li>
            <li><strong>Projects (10%)</strong> – Mini projects, main projects, and other contributions.</li>
            <li><strong>Activities (20%)</strong> – Hackathons, competitions, workshops, and participations.</li>
            <li><strong>Discipline (10%)</strong> – Attendance record and GPA improvement trend.</li>
        </ul>
        <br>
        <a href="SGI.pdf" target="_blank" class="btn-primary" style="width:auto;display:inline-block;padding:10px 24px;">📄 View SGI Documentation (PDF)</a>
    </div>

    <!-- ALL SEMESTER ANALYSIS GUIDELINES -->
    <div class="section" style="margin-top:24px;">
        <h2>All Semester Analysis</h2>
        <p>The All Semester Analysis evaluates your overall performance across semesters using the following criteria:</p>
        <br>
        <ul class="about-list">
            <li><strong>Best & Worst Semester</strong> – Determined by your <strong>SGI Score</strong>. The semester with the highest SGI is your best and the lowest is your worst.</li>
            <li><strong>Best & Worst Subject</strong> – Determined by a combined score based on CAT and CA performance. Both must be available for a subject to be ranked.</li>
            <li><strong>Avg CAT Marks</strong> – Average of CAT 1, CAT 2, and CAT 3 scores (each out of 100) for that subject.</li>
            <li><strong>CA Marks</strong> – Final Continuous Assessment mark converted to a percentage out of 100.</li>
        </ul>
        <br>
    </div>

    <!-- SGI GRADE SCALE -->
    <div class="section" style="margin-top:24px;">
        <h2>SGI Grade Scale</h2>
        <table class="grade-table">
            <tr><th>SGI Score</th><th>Grade</th></tr>
            <tr><td>9.0 – 10.0</td><td>O (Excellent)</td></tr>
            <tr><td>8.0 – 8.9</td><td>A (Very Good)</td></tr>
            <tr><td>7.0 – 7.9</td><td>B (Good)</td></tr>
            <tr><td>6.0 – 6.9</td><td>C (Average)</td></tr>
            <tr><td>Below 6.0</td><td>D (Needs Improvement)</td></tr>
        </table>
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
