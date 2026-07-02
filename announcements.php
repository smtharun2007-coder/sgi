<?php
include 'config.php';
requireLogin();
$u = $_SESSION['user'];
$mentor_id = $u['mentor_id'] ?? '';

// Fetch announcements from mentor
$list = [];
if ($mentor_id) {
    $cur = $announcements->find(
        ['mentor_id' => $mentor_id],
        ['sort' => ['created_at' => -1]]
    );
    $list = iterator_to_array($cur);
}

// Submit approval request
$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['request_approval'])) {
    $subject = trim($_POST['subject'] ?? '');
    $reason  = trim($_POST['reason']  ?? '');
    if ($subject && $reason && $mentor_id) {
        $approvals->insertOne([
            'roll'      => $u['roll'],
            'name'      => $u['name'],
            'mentor_id' => $mentor_id,
            'subject'   => $subject,
            'reason'    => $reason,
            'status'    => 'pending',
            'created_at'=> new MongoDB\BSON\UTCDateTime()
        ]);
        // Notify mentor
        $notifications->insertOne([
            'mentor_id' => $mentor_id,
            'message'   => "📋 Approval request from {$u['name']} ({$u['roll']}): $subject",
            'type'      => 'approval',
            'read'      => false,
            'created_at'=> new MongoDB\BSON\UTCDateTime()
        ]);
        $msg = 'success';
    }
}

// My approval requests
$myApprovals = iterator_to_array($approvals->find(['roll'=>$u['roll']],['sort'=>['created_at'=>-1]]));

$unreadCount = $notifications->countDocuments(['roll'=>$u['roll'],'read'=>false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Announcements</title>
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
        <a href="calendar.php">Calendar</a>
        <a href="announcements.php" style="color:#fff;font-weight:700;">Announcements</a>
        <a href="update_profile.php">Profile</a>
        <a href="print_select.php">Print</a>
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
                🔔<?php if($unreadCount>0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <a href="#" onclick="markAll()">Mark all read</a></div>
                <div id="notifList"><div class="notif-empty">Loading…</div></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">

    <div class="page-tabs">
        <a href="#announcements" class="page-tab active">📢 Announcements</a>
        <a href="#approvals" class="page-tab">📋 My Requests</a>
        <a href="#new-request" class="page-tab">+ New Request</a>
    </div>

    <!-- ANNOUNCEMENTS -->
    <div id="announcements">
        <h3 style="color:#1a1a2e;margin-bottom:16px;">Announcements from Mentor</h3>
        <?php if(empty($list)): ?>
            <p class="no-data">No announcements yet.</p>
        <?php else: ?>
            <?php foreach($list as $a):
                $typeClass = $a['type'] ?? 'urgent';
                $badgeClass = 'badge-'.($typeClass==='urgent'?'urgent':($typeClass==='info'?'info':'general'));
                $cardClass  = $typeClass==='info'?'info':($typeClass==='general'?'success':'');
            ?>
            <div class="announce-card <?= $cardClass ?>">
                <div class="announce-title">
                    <?= htmlspecialchars($a['title']) ?>
                    <span class="<?= $badgeClass ?>"><?= ucfirst($typeClass) ?></span>
                </div>
                <div class="announce-body"><?= nl2br(htmlspecialchars($a['body'])) ?></div>
                <div class="announce-meta">
                    Posted by <?= htmlspecialchars($a['mentor_name'] ?? 'Mentor') ?> &nbsp;·&nbsp;
                    <?= date('d M Y, h:i A', $a['created_at']->toDateTime()->getTimestamp()) ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <hr style="margin:32px 0;">

    <!-- MY APPROVAL REQUESTS -->
    <div id="approvals">
        <h3 style="color:#1a1a2e;margin-bottom:16px;">My Approval Requests</h3>
        <?php if(empty($myApprovals)): ?>
            <p class="no-data">No requests submitted yet.</p>
        <?php else: ?>
            <?php foreach($myApprovals as $ap): ?>
            <div class="approval-card">
                <div class="approval-info">
                    <div class="a-title"><?= htmlspecialchars($ap['subject']) ?></div>
                    <div class="a-sub"><?= htmlspecialchars($ap['reason']) ?></div>
                    <div class="a-sub" style="margin-top:4px;color:#aaa;"><?= date('d M Y', $ap['created_at']->toDateTime()->getTimestamp()) ?></div>
                    <?php if(!empty($ap['mentor_remark'])): ?>
                        <div class="a-sub" style="margin-top:6px;color:#555;">Mentor: <?= htmlspecialchars($ap['mentor_remark']) ?></div>
                    <?php endif; ?>
                </div>
                <span class="status-<?= $ap['status'] ?>"><?= ucfirst($ap['status']) ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <hr style="margin:32px 0;">

    <!-- NEW REQUEST FORM -->
    <div id="new-request">
        <h3 style="color:#1a1a2e;margin-bottom:16px;">Submit Approval Request to Mentor</h3>
        <?php if($msg==='success'): ?>
            <p class="success">Request submitted successfully. Your mentor will review it.</p>
        <?php endif; ?>
        <?php if(!$mentor_id): ?>
            <p class="error">No mentor assigned to your account.</p>
        <?php else: ?>
        <div class="form-box" style="padding:28px;">
            <form method="POST">
                <label>Subject / Title</label>
                <input type="text" name="subject" placeholder="e.g. Leave request, Re-exam request…" required>
                <label>Reason / Details</label>
                <textarea name="reason" rows="4" placeholder="Explain your request…" required></textarea>
                <button type="submit" name="request_approval" class="btn-primary" style="margin-top:16px;">Submit Request</button>
            </form>
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
// Smooth scroll for tabs
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
