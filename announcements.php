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
        <a href="update_profile.php">Profile</a>
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

    <div class="page-tabs">
        <a href="#announcements" class="page-tab active">&#128226; Announcements</a>
    </div>

    <!-- ANNOUNCEMENTS -->
    <div id="announcements">
        <?php if(empty($list)): ?>
            <p class="no-data">No announcements yet.</p>
        <?php else: ?>
            <?php foreach($list as $a):
                $typeClass  = $a['type'] ?? 'general';
                $knownTypes = ['urgent','info','general'];
                $badgeClass = in_array($typeClass,$knownTypes) ? 'badge-'.$typeClass : 'badge-general';
                $cardClass  = $typeClass==='info'?'info':($typeClass==='general'?'success':'');
            ?>
            <div class="announce-card <?= $cardClass ?>">
                <div class="announce-title">
                    <?= htmlspecialchars($a['title']) ?>
                    <span class="<?= $badgeClass ?>"><?= ucfirst($typeClass) ?></span>
                </div>
                <div class="announce-body"><?= nl2br(htmlspecialchars($a['body'])) ?></div>
                <?php if(!empty($a['attachments'])): ?>
                <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach($a['attachments'] as $att): ?>
                        <?php $isImg = ($att['type']==='image'); ?>
                        <a href="<?= htmlspecialchars($att['url']) ?>" target="_blank"
                           style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#f0f2f5;border-radius:8px;font-size:12px;color:#1a1a2e;text-decoration:none;border:1px solid #e0e0e0;">
                            <?= $isImg ? '&#128444;' : '&#128196;' ?> <?= htmlspecialchars($att['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="announce-meta">
                    Posted by <?= htmlspecialchars($a['mentor_name'] ?? 'Mentor') ?> &nbsp;·&nbsp;
                    <?= date('d M Y, h:i A', $a['created_at']->toDateTime()->getTimestamp()) ?>
                </div>
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
