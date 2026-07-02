<?php
include 'config.php';
requireLogin();
$u = $_SESSION['user'];
$mentor_id = $u['mentor_id'] ?? '';

$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$firstDay   = mktime(0,0,0,$month,1,$year);
$daysInMonth = (int)date('t', $firstDay);
$startDow    = (int)date('w', $firstDay); // 0=Sun

// Fetch events for this mentor + month
$events = [];
if ($mentor_id) {
    $from = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,$month,1,$year)*1000);
    $to   = new MongoDB\BSON\UTCDateTime(mktime(23,59,59,$month,$daysInMonth,$year)*1000);
    $cur  = $calendar_events->find(['mentor_id'=>$mentor_id,'date'=>['$gte'=>$from,'$lte'=>$to]]);
    foreach ($cur as $ev) {
        $day = (int)date('j', $ev['date']->toDateTime()->getTimestamp());
        $events[$day][] = $ev;
    }
}

$typeColors = ['exam'=>'exam','holiday'=>'holiday','study'=>'study','event'=>'event','other'=>'other'];
$monthName  = date('F Y', $firstDay);
$prev = ['month'=>$month-1==0?12:$month-1, 'year'=>$month-1==0?$year-1:$year];
$next = ['month'=>$month+1==13?1:$month+1, 'year'=>$month+1==13?$year+1:$year];

// Unread notification count
$unreadCount = $notifications->countDocuments(['roll'=>$u['roll'],'read'=>false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Calendar</title>
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
        <a href="calendar.php" style="color:#fff;font-weight:700;">Calendar</a>
        <a href="announcements.php">Announcements</a>
        <a href="update_profile.php">Profile</a>
        <a href="print_select.php">Print</a>
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
                🔔<?php if($unreadCount>0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <a href="notifications.php?mark_all=1" onclick="markAll()">Mark all read</a></div>
                <div id="notifList"><div class="notif-empty">Loading…</div></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
    <div class="form-box">
        <div class="cal-nav">
            <a href="calendar.php?month=<?= $prev['month'] ?>&year=<?= $prev['year'] ?>" class="cal-nav-btn">&#8592; Prev</a>
            <h2><?= $monthName ?></h2>
            <a href="calendar.php?month=<?= $next['month'] ?>&year=<?= $next['year'] ?>" class="cal-nav-btn">Next &#8594;</a>
        </div>

        <div class="cal-grid">
            <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                <div class="cal-day-name"><?= $d ?></div>
            <?php endforeach; ?>

            <?php for($i=0;$i<$startDow;$i++): ?>
                <div class="cal-cell empty"></div>
            <?php endfor; ?>

            <?php
            $today = (int)date('j'); $todayM = (int)date('n'); $todayY = (int)date('Y');
            for($d=1;$d<=$daysInMonth;$d++):
                $isToday = ($d==$today && $month==$todayM && $year==$todayY);
            ?>
            <div class="cal-cell <?= $isToday?'today':'' ?>">
                <div class="cal-date"><?= $d ?></div>
                <?php if(!empty($events[$d])): ?>
                    <?php foreach($events[$d] as $ev): ?>
                        <span class="cal-event-dot <?= htmlspecialchars($ev['type']) ?>" title="<?= htmlspecialchars($ev['title']) ?>">
                            <?= htmlspecialchars($ev['title']) ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>

        <div class="cal-legend">
            <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#e94560"></span> Exam</span>
            <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#27ae60"></span> Holiday</span>
            <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#f5a623"></span> Study Holiday</span>
            <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#2980b9"></span> Event</span>
            <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#8e44ad"></span> Other</span>
        </div>

        <?php if(!$mentor_id): ?>
            <p class="no-data" style="margin-top:20px;">No mentor assigned. Calendar events will appear once a mentor is linked to your account.</p>
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
function markAll() {
    fetch('notifications.php?mark_all=1');
    document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread'));
    const badge = document.querySelector('.notif-badge');
    if(badge) badge.remove();
}
document.addEventListener('click', e => {
    if (!document.getElementById('bellBtn').contains(e.target) && !document.getElementById('notifDrop').contains(e.target))
        document.getElementById('notifDrop').classList.remove('open');
});
</script>
<div class="copyright-footer">&copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.</div>
</body>
</html>
