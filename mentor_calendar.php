<?php
include 'config.php';
if (!isset($_SESSION['mentor'])) { header("Location: mentor_login.php"); exit; }
$m = $_SESSION['mentor'];

$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

// Add event (supports multiple dates)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_event'])) {
    $dates = $_POST['event_dates'] ?? [];
    $title = trim($_POST['title'] ?? '');
    $type  = trim($_POST['type_custom'] ?? '') ?: (trim($_POST['type_select'] ?? '') ?: 'other');
    $type  = ($type === '__custom') ? 'other' : $type;
    $desc  = trim($_POST['desc'] ?? '');
    $color = trim($_POST['color'] ?? '#e94560');
    if (!empty($dates) && $title) {
        $students = iterator_to_array($users->find(['mentor_id' => $m['mentor_id']]));
        foreach ($dates as $date) {
            $ts = strtotime($date);
            if (!$ts) continue;
            $calendar_events->insertOne([
                'mentor_id' => $m['mentor_id'],
                'date'      => new MongoDB\BSON\UTCDateTime($ts * 1000),
                'title'     => $title,
                'type'      => $type,
                'color'     => $color,
                'desc'      => $desc,
                'created_at'=> new MongoDB\BSON\UTCDateTime()
            ]);
            foreach ($students as $st) {
                $notifications->insertOne([
                    'roll'    => $st['roll'],
                    'message' => "📅 New calendar event: $title on " . date('d M Y', $ts),
                    'type'    => 'calendar',
                    'read'    => false,
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ]);
            }
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

// Fetch all previously used types by this mentor
$allTypes = [];
$typeCursor = $calendar_events->find(
    ['mentor_id' => $m['mentor_id']],
    ['projection' => ['type'=>1,'color'=>1], 'sort' => ['created_at'=>-1]]
);
foreach ($typeCursor as $ev) {
    $t = $ev['type'] ?? '';
    if ($t && !isset($allTypes[$t])) $allTypes[$t] = $ev['color'] ?? '#e94560';
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
                <div class="notif-dropdown-header">Notifications <a href="#" onclick="markAll(event)">Mark all read</a></div>
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
        <form method="POST">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label>Title</label>
                    <input type="text" name="title" placeholder="e.g. CAT 1 Exam" required>
                </div>
                <div>
                    <label>Type</label>
                    <select name="type_select" id="typeSelect" onchange="syncType(this.value)">
                        <?php foreach($allTypes as $tName => $tColor): ?>
                        <option value="<?= htmlspecialchars($tName) ?>" data-color="<?= htmlspecialchars($tColor) ?>"><?= htmlspecialchars(ucfirst($tName)) ?></option>
                        <?php endforeach; ?>
                        <option value="__new">+ Create new type…</option>
                    </select>
                    <div id="newTypeWrap" style="display:<?= empty($allTypes)?'flex':'none' ?>;gap:8px;margin-top:6px;align-items:center;">
                        <input type="text" name="type_custom" id="typeCustom" placeholder="New type name" style="margin:0;flex:1;">
                        <input type="color" name="color" id="colorPicker" value="#e94560" style="width:46px;height:46px;padding:4px;border-radius:10px;border:2px solid #eee;cursor:pointer;flex-shrink:0;">
                    </div>
                </div>
                <div>
                    <label>Description (optional)</label>
                    <input type="text" name="desc" placeholder="Details shown to students…">
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <button type="submit" name="add_event" class="btn-primary" style="margin-top:0;width:100%;padding:14px 20px;">Add to Selected Dates</button>
                </div>
            </div>
            <div>
                <label>Select Dates (click to toggle, can select multiple)</label>
                <div id="datePickerWrap" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;padding:16px;background:#f9f9f9;border-radius:10px;border:2px solid #eee;">
                    <?php
                    $daysInFormMonth = (int)date('t', $firstDay);
                    for($fd=1;$fd<=$daysInFormMonth;$fd++):
                        $dateVal = sprintf('%04d-%02d-%02d',$year,$month,$fd);
                        $dayName = date('D', mktime(0,0,0,$month,$fd,$year));
                    ?>
                    <label style="cursor:pointer;text-align:center;">
                        <input type="checkbox" name="event_dates[]" value="<?= $dateVal ?>" style="display:none;" class="date-cb">
                        <span class="date-chip" data-val="<?= $dateVal ?>">
                            <span style="font-size:10px;display:block;color:#888;"><?= $dayName ?></span>
                            <span style="font-size:15px;font-weight:700;"><?= $fd ?></span>
                        </span>
                    </label>
                    <?php endfor; ?>
                </div>
                <div style="margin-top:8px;font-size:12px;color:#888;">Selected: <span id="selectedCount">0</span> date(s)</div>
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
                $cellBg = !empty($events[$d]) ? htmlspecialchars($events[$d][0]['color'] ?? '#e94560') : '';
            ?>
            <div class="cal-cell <?= $isToday?'today':'' ?>" style="<?= $cellBg ? 'background:'.$cellBg.';' : '' ?>">
                <div class="cal-date" style="text-align:center;<?= $cellBg ? 'color:#fff;font-weight:700;' : '' ?>"><?= $d ?></div>
                <?php if(!empty($events[$d])): ?>
                    <?php foreach($events[$d] as $ev): ?>
                        <span style="display:block;font-size:10px;color:#fff;font-weight:600;text-align:center;padding:1px 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= htmlspecialchars($ev['title']) ?>
                            <a href="mentor_calendar.php?delete=<?= (string)$ev['_id'] ?>&month=<?= $month ?>&year=<?= $year ?>" onclick="return confirm('Delete?')" style="color:#fff;font-weight:700;text-decoration:none;">×</a>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>

        <?php
        // Build legend from only types used this month
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
    </div>
</div>
<script>
const savedTypes = <?= json_encode(array_map(fn($t,$c)=>['type'=>$t,'color'=>$c], array_keys($allTypes), array_values($allTypes))) ?>;
function syncType(val) {
    const wrap = document.getElementById('newTypeWrap');
    const colorPicker = document.getElementById('colorPicker');
    if (val === '__new') {
        wrap.style.display = 'flex';
    } else {
        wrap.style.display = 'none';
        document.getElementById('typeCustom').value = '';
        const found = savedTypes.find(t => t.type === val);
        if (found) colorPicker.value = found.color;
    }
}
// Init color from first option
window.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('typeSelect');
    if (sel && sel.value !== '__new') syncType(sel.value);
});
document.querySelectorAll('.date-cb').forEach(cb => {
    cb.addEventListener('change', function() {
        const chip = this.nextElementSibling;
        chip.style.background = this.checked ? '#1a1a2e' : '#fff';
        chip.style.color = this.checked ? '#fff' : '#333';
        document.getElementById('selectedCount').textContent =
            document.querySelectorAll('.date-cb:checked').length;
    });
});
// Style chips
document.querySelectorAll('.date-chip').forEach(chip => {
    chip.style.cssText = 'display:inline-block;padding:8px 10px;border-radius:8px;border:2px solid #eee;background:#fff;color:#333;min-width:44px;transition:all 0.15s;';
});
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
document.addEventListener('click', e => {
    if (!document.getElementById('bellBtn').contains(e.target) && !document.getElementById('notifDrop').contains(e.target))
        document.getElementById('notifDrop').classList.remove('open');
});
</script>
<div class="copyright-footer">&copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.</div>
</body>
</html>
