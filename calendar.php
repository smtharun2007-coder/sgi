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
        <a href="update_profile.php">Profile</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="print_select.php">Print</a>
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
                🔔<?php if($unreadCount>0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <a href="#" onclick="markAll(event)">Mark all read</a></div>
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
                $cellBg = !empty($events[$d]) ? htmlspecialchars($events[$d][0]['color'] ?? '#e94560') : '';
            ?>
            <div class="cal-cell <?= $isToday?'today':'' ?>" style="<?= $cellBg ? 'background:'.$cellBg.';' : '' ?>" <?= !empty($events[$d]) ? 'onclick="showDay('.$d.')" style="background:'.$cellBg.';cursor:pointer;"' : '' ?>>
                <div class="cal-date" style="text-align:center;<?= $cellBg ? 'color:#fff;font-weight:700;' : '' ?>"><?= $d ?></div>
                <?php if(!empty($events[$d])): ?>
                    <?php foreach($events[$d] as $ev): ?>
                        <span style="display:block;font-size:10px;color:#fff;font-weight:600;text-align:center;padding:1px 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= htmlspecialchars($ev['title']) ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>

        <?php
        // Legend: only types used this month
        $usedTypes = [];
        foreach($events as $dayEvs) {
            foreach($dayEvs as $ev) {
                $key = $ev['type'];
                if (!isset($usedTypes[$key])) $usedTypes[$key] = $ev['color'] ?? '#e94560';
            }
        }
        ?>
        <?php if(!empty($usedTypes)): ?>
        <div class="cal-legend">
            <?php foreach($usedTypes as $typeName => $typeColor): ?>
            <span class="cal-legend-item">
                <span class="cal-legend-dot" style="background:<?= htmlspecialchars($typeColor) ?>"></span>
                <?= htmlspecialchars(ucfirst($typeName)) ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if(!$mentor_id): ?>
            <p class="no-data" style="margin-top:20px;">No mentor assigned. Calendar events will appear once a mentor is linked to your account.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Day detail modal -->
<div id="dayModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:28px 32px;width:90%;max-width:440px;box-shadow:0 12px 40px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 id="modalDate" style="color:#1a1a2e;font-size:16px;"></h3>
            <button onclick="closeModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#888;">&times;</button>
        </div>
        <div id="modalEvents"></div>
    </div>
</div>
<script>
const calEvents = <?php
    $jsEvents = [];
    foreach($events as $day => $evList) {
        foreach($evList as $ev) {
            $jsEvents[$day][] = [
                'title' => $ev['title'],
                'type'  => $ev['type'],
                'color' => $ev['color'] ?? '#e94560',
                'desc'  => $ev['desc'] ?? ''
            ];
        }
    }
    echo json_encode($jsEvents);
?>;
const monthName = '<?= $monthName ?>';
function showDay(d) {
    const evs = calEvents[d];
    if (!evs || !evs.length) return;
    document.getElementById('modalDate').textContent = d + ' ' + monthName;
    document.getElementById('modalEvents').innerHTML = evs.map(e => `
        <div style="display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-bottom:1px solid #f0f2f5;">
            <span style="flex-shrink:0;padding:4px 10px;border-radius:8px;background:${e.color};color:#fff;font-size:11px;font-weight:700;">${e.type.toUpperCase()}</span>
            <div>
                <div style="font-weight:700;color:#1a1a2e;font-size:14px;">${e.title}</div>
                ${e.desc ? `<div style="font-size:12px;color:#666;margin-top:4px;">${e.desc}</div>` : ''}
            </div>
        </div>`).join('');
    document.getElementById('dayModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('dayModal').style.display = 'none';
}
document.getElementById('dayModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
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
    if (!document.getElementById('bellBtn').contains(e.target) && !document.getElementById('notifDrop').contains(e.target))
        document.getElementById('notifDrop').classList.remove('open');
});
</script>
<div class="copyright-footer">&copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.</div>
</body>
</html>
