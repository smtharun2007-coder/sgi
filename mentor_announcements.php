<?php
include 'config.php';
if (!isset($_SESSION['mentor'])) { header("Location: mentor_login.php"); exit; }
$m = $_SESSION['mentor'];

// Post announcement
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['post_announcement'])) {
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body']  ?? '');
    $type  = $_POST['type'] ?? 'general';
    if ($title && $body) {
        $announcements->insertOne([
            'mentor_id'   => $m['mentor_id'],
            'mentor_name' => $m['name'],
            'title'       => $title,
            'body'        => $body,
            'type'        => $type,
            'created_at'  => new MongoDB\BSON\UTCDateTime()
        ]);
        // Notify all students
        $students = $users->find(['mentor_id' => $m['mentor_id']]);
        foreach ($students as $st) {
            $notifications->insertOne([
                'roll'    => $st['roll'],
                'message' => "📢 New announcement: $title",
                'type'    => 'announcement',
                'read'    => false,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);
        }
        header("Location: mentor_announcements.php");
        exit;
    }
}

// Delete announcement
if (isset($_GET['delete_ann'])) {
    $announcements->deleteOne(['_id'=>new MongoDB\BSON\ObjectId($_GET['delete_ann']),'mentor_id'=>$m['mentor_id']]);
    header("Location: mentor_announcements.php");
    exit;
}

// Approve / Reject
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $remark = trim($_GET['remark'] ?? '');
    if (in_array($action, ['approved','rejected'])) {
        $ap = $approvals->findOne(['_id'=>new MongoDB\BSON\ObjectId($_GET['id']),'mentor_id'=>$m['mentor_id']]);
        if ($ap) {
            $approvals->updateOne(
                ['_id'=>new MongoDB\BSON\ObjectId($_GET['id'])],
                ['$set'=>['status'=>$action,'mentor_remark'=>$remark,'updated_at'=>new MongoDB\BSON\UTCDateTime()]]
            );
            $emoji = $action==='approved' ? '✅' : '❌';
            $notifications->insertOne([
                'roll'    => $ap['roll'],
                'message' => "$emoji Your request \"{$ap['subject']}\" has been $action by your mentor.",
                'type'    => 'approval',
                'read'    => false,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);
        }
    }
    header("Location: mentor_announcements.php");
    exit;
}

$myAnnouncements = iterator_to_array($announcements->find(['mentor_id'=>$m['mentor_id']],['sort'=>['created_at'=>-1]]));
$pendingApprovals = iterator_to_array($approvals->find(['mentor_id'=>$m['mentor_id'],'status'=>'pending'],['sort'=>['created_at'=>-1]]));
$pastApprovals    = iterator_to_array($approvals->find(['mentor_id'=>$m['mentor_id'],'status'=>['$in'=>['approved','rejected']]],['sort'=>['updated_at'=>-1],'limit'=>20]));

$unreadCount = $notifications->countDocuments(['mentor_id'=>$m['mentor_id'],'read'=>false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Mentor Announcements</title>
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
        <a href="mentor_calendar.php">Calendar</a>
        <a href="mentor_announcements.php" style="color:#fff;font-weight:700;">Announcements</a>
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

    <div class="page-tabs">
        <a href="#post" class="page-tab active">📢 Post Announcement</a>
        <a href="#pending" class="page-tab">
            📋 Pending Requests
            <?php if(count($pendingApprovals)>0): ?>
                <span class="notif-badge" style="position:relative;top:0;right:0;margin-left:6px;"><?= count($pendingApprovals) ?></span>
            <?php endif; ?>
        </a>
        <a href="#history" class="page-tab">✅ Past Requests</a>
        <a href="#my-announcements" class="page-tab">📄 My Announcements</a>
    </div>

    <!-- POST ANNOUNCEMENT -->
    <div id="post">
        <div class="form-box" style="padding:28px;margin-bottom:24px;">
            <h2 style="margin-bottom:16px;">Post Announcement</h2>
            <form method="POST">
                <label>Title</label>
                <input type="text" name="title" placeholder="Announcement title…" required>
                <label>Message</label>
                <textarea name="body" rows="4" placeholder="Write your announcement…" required></textarea>
                <label>Type</label>
                <select name="type">
                    <option value="urgent">Urgent</option>
                    <option value="info">Info</option>
                    <option value="general" selected>General</option>
                </select>
                <button type="submit" name="post_announcement" class="btn-primary" style="margin-top:16px;">Post to All My Students</button>
            </form>
        </div>
    </div>

    <!-- PENDING APPROVALS -->
    <div id="pending">
        <h3 style="color:#1a1a2e;margin-bottom:16px;">Pending Approval Requests</h3>
        <?php if(empty($pendingApprovals)): ?>
            <p class="no-data">No pending requests.</p>
        <?php else: ?>
            <?php foreach($pendingApprovals as $ap): ?>
            <div class="approval-card">
                <div class="approval-info">
                    <div class="a-title"><?= htmlspecialchars($ap['subject']) ?></div>
                    <div class="a-sub">From: <?= htmlspecialchars($ap['name']) ?> (<?= htmlspecialchars($ap['roll']) ?>)</div>
                    <div class="a-sub"><?= htmlspecialchars($ap['reason']) ?></div>
                    <div class="a-sub" style="color:#aaa;"><?= date('d M Y, h:i A', $ap['created_at']->toDateTime()->getTimestamp()) ?></div>
                </div>
                <div class="approval-actions">
                    <form method="GET" style="display:inline;">
                        <input type="hidden" name="action" value="approved">
                        <input type="hidden" name="id" value="<?= (string)$ap['_id'] ?>">
                        <input type="text" name="remark" placeholder="Remark (optional)" style="width:160px;padding:8px 10px;font-size:12px;margin:0 8px 0 0;">
                        <button type="submit" class="btn-approve">✅ Approve</button>
                    </form>
                    <form method="GET" style="display:inline;">
                        <input type="hidden" name="action" value="rejected">
                        <input type="hidden" name="id" value="<?= (string)$ap['_id'] ?>">
                        <input type="text" name="remark" placeholder="Reason (optional)" style="width:160px;padding:8px 10px;font-size:12px;margin:0 8px 0 0;">
                        <button type="submit" class="btn-reject">❌ Reject</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <hr style="margin:32px 0;">

    <!-- PAST APPROVALS -->
    <div id="history">
        <h3 style="color:#1a1a2e;margin-bottom:16px;">Past Requests</h3>
        <?php if(empty($pastApprovals)): ?>
            <p class="no-data">No past requests.</p>
        <?php else: ?>
            <?php foreach($pastApprovals as $ap): ?>
            <div class="approval-card">
                <div class="approval-info">
                    <div class="a-title"><?= htmlspecialchars($ap['subject']) ?></div>
                    <div class="a-sub">From: <?= htmlspecialchars($ap['name']) ?> (<?= htmlspecialchars($ap['roll']) ?>)</div>
                    <?php if(!empty($ap['mentor_remark'])): ?>
                        <div class="a-sub" style="margin-top:4px;">Remark: <?= htmlspecialchars($ap['mentor_remark']) ?></div>
                    <?php endif; ?>
                </div>
                <span class="status-<?= $ap['status'] ?>"><?= ucfirst($ap['status']) ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <hr style="margin:32px 0;">

    <!-- MY ANNOUNCEMENTS -->
    <div id="my-announcements">
        <h3 style="color:#1a1a2e;margin-bottom:16px;">My Announcements</h3>
        <?php if(empty($myAnnouncements)): ?>
            <p class="no-data">No announcements posted yet.</p>
        <?php else: ?>
            <?php foreach($myAnnouncements as $a):
                $typeClass = $a['type'] ?? 'general';
                $cardClass = $typeClass==='info'?'info':($typeClass==='general'?'success':'');
            ?>
            <div class="announce-card <?= $cardClass ?>" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
                <div style="flex:1;">
                    <div class="announce-title"><?= htmlspecialchars($a['title']) ?> <span class="badge-<?= $typeClass ?>"><?= ucfirst($typeClass) ?></span></div>
                    <div class="announce-body"><?= nl2br(htmlspecialchars($a['body'])) ?></div>
                    <div class="announce-meta"><?= date('d M Y, h:i A', $a['created_at']->toDateTime()->getTimestamp()) ?></div>
                </div>
                <a href="mentor_announcements.php?delete_ann=<?= (string)$a['_id'] ?>"
                   onclick="return confirm('Delete this announcement?')"
                   style="color:#e94560;font-size:18px;text-decoration:none;flex-shrink:0;">🗑</a>
            </div>
            <?php endforeach; ?>
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
document.querySelectorAll('.page-tab').forEach(tab => {
    tab.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href.startsWith('#')) {
            e.preventDefault();
            document.querySelector(href)?.scrollIntoView({behavior:'smooth'});
            document.querySelectorAll('.page-tab').forEach(t=>t.classList.remove('active'));
            this.classList.add('active');
        }
    });
});
</script>
<div class="copyright-footer">&copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.</div>
</body>
</html>
