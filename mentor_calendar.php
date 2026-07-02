<?php
include 'config.php';
if (!isset($_SESSION['mentor'])) { header("Location: mentor_login.php"); exit; }
$m = $_SESSION['mentor'];

$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

// Add event
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_event'])) {
    $date = $_POST['event_date'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $type  = $_POST['type'] ?? 'other';
    $desc  = trim($_POST['desc'] ?? '');
    if ($date && $title) {
        $ts = strtotime($date);
        $calendar_events->insertOne([
            'mentor_id' => $m['mentor_id'],
            'date'      => new MongoDB\BSON\UTCDateTime($ts * 1000),
            'title'     => $title,
            'type'      => $type,
            'desc'      => $desc,
            'created_at'=> new MongoDB\BSON\UTCDateTime()
        ]);
        // Notify all students of this mentor
        $students = $users->find(['mentor_id' => $m['mentor_id']]);
        foreach ($students as $st) {
            $notifications->insertOne([
                'roll'    => $st['roll'],
                'message' => "📅 New calendar event: $title on " . date('d M Y', $ts),
                'type'    => 'calendar',
                'read'    => false,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);
        }
        header("Location: mentor_calendar.php?month=$month&year=$year");
        exit;
    }
}

// Delete event
if (isset($_GET['delete'])) {
    $calendar_events->deleteOne(['_id'=>new MongoDB\BSON\ObjectId($_GET['delete']),'mentor_id'=>$m['mentor_id']]);
    header("Location: mentor_calendar.php?month=$month&year=$year");
    exit;
}

$firstDay    = mktime(0,0,0,$month,1,$year);
$daysInMonth = (int)date('t', $firstDay);
$startDow    = (int)date('w', $firstDay);

$events = [];
$from = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,$month,1,$year)*1000);
$to   = new MongoDB\BSON\UTCDateTime(mktime(23,59,59,$month,$daysInMonth,$year)*1000);
$cur  = $calendar_events->find(['mentor_id'=>$m['mentor_id'],'date'=>['$gte'=>$from,'$lte'=>$to]]);
foreach ($cur as $ev) {
    $day = (int)date('j', $ev['date']->toDateTime()->getTimestamp());
    $events[$day][] = $ev;
}

$monthName = date('F Y', $firstDay);
$prev = ['month'=>$month-1==0?12:$month-1,'year'=>$month-1==0?$year-1:$year];
$next = ['month'=>$month+1==13?1:$month+1,'year'=>$month+1==13?$year+1:$year];

$unreadCount = $notifications->countDocuments(['mentor_id'=>$m['mentor_id'],'read'=>false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Mentor Calendar</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<nav class="navbar" style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);">
    <a href="mentor_dashboard.php" class="nav-brand">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo"> SGI <span style="font-size:13px;opacity:0.7;font-weight:400;">Mentor</span>
    </a>
    <div class="nav-links">
        <a href="mentor_dashboard.php">Home</a>
        <a href="mentor_calendar.php" style="color:#fff;font-weight:700;">Calendar</a>
        <a href="mentor_announcements.php">Announcements</a>
        <a href="mentor_update_profile.php">Profile</a>
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
                🔔<?php if($unreadCount>0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <a href="#" onclick="markAll()">Mark all read</a></div>
                <div id="notifList"><div class="notif-empty">Loading…</div></div>
            </div>
        </div>
        <a href="mentor_logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
    <!-- Add Event Form -->
    <div class="form-box" style="margin-bottom:24px;">
        <h2 style="margin-bottom:16px;">Add Calendar Event</h2>
        <form method="POST" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:12px;align-items:end;">
            <div>
                <label>Date</label>
                <input type="date" name="event_date" required>
            </div>
            <div>
                <label>Title</label>
                <input type="text" name="title" placeholder="e.g. CAT 1 Exam" required>
            </div>
            <div>
                <label>Type</label>
                <select name="type">
                    <option value="exam">Exam</option>
                    <option value="holiday">Holiday</option>
                    <option value="study">Study Holiday</option>
                    <option value="event">Event</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label>Description (optional)</label>
                <input type="text" name="desc" placeholder="Details…">
            </div>
            <div>
                <button type="submit" name="add_event" class="btn-primary" style="margin-top:0;width:auto;padding:14px 20px;">Add</button>
            </div>
        </form>
    </div>

    <!-- Calendar -->
    <div class="form-box">
        <div class="cal-nav">
            <a href="mentor_calendar.php?month=<?= $prev['month'] ?>&year=<?= $prev['year'] ?>" class="cal-nav-btn">&#8592; Prev</a>
            <h2><?= $monthName ?></h2>
            <a href="mentor_calendar.php?month=<?= $next['month'] ?>&year=<?= $next['year'] ?>" class="cal-nav-btn">Next &#8594;</a>
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
                        <span class="cal-event-dot <?= htmlspecialchars($ev['type']) ?>">
                            <?= htmlspecialchars($ev['title']) ?>
                            <a href="mentor_calendar.php?delete=<?= (string)$ev['_id'] ?>&month=<?= $month ?>&year=<?= $year ?>"
                               onclick="return confirm('Delete this event?')"
                               style="color:#fff;margin-left:4px;font-weight:700;text-decoration:none;">×</a>
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
    </div>
</div>
<script>
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
function markAll() {
    fetch('notifications.php?mark_all=1&mentor=1');
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
