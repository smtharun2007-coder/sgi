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
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="print_select.php">Print</a>
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
                🔔<?php if($unreadCount>0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <span style="display:flex;gap:10px;"><a href="#" onclick="markAll(event)">Mark read</a><a href="#" onclick="clearAll(event)">Clear all</a></span></div>
                <div class="notif-list-scroll" id="notifList"><div class="notif-empty">Loading…</div></div>
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
                $typeClass = $a['type'] ?? 'general';
                $color     = $a['color'] ?? '#e94560';
            ?>
            <div class="announce-card" style="border-left:4px solid <?= $color ?>;background:#fff;padding:16px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:16px;">
                <div class="announce-title">
                    <?= htmlspecialchars($a['title']) ?>
                    <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;color:#fff;background:<?= $color ?>;"><?= ucfirst($typeClass) ?></span>
                </div>
                <div class="announce-body"><?= nl2br(htmlspecialchars($a['body'])) ?></div>
                <?php if(!empty($a['attachments'])): ?>
                <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach($a['attachments'] as $att): ?>
                        <?php $isImg = ($att['type']==='image'); ?>
                        <?php if($isImg): ?>
                            <a href="#" onclick="showImg('<?= htmlspecialchars($att['url']) ?>');return false;"
                               style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#f0f2f5;border-radius:8px;font-size:12px;color:#1a1a2e;text-decoration:none;border:1px solid #e0e0e0;">
                                &#128444; <?= htmlspecialchars($att['name']) ?>
                            </a>
                        <?php else: ?>
                            <a href="#" onclick="showDoc('<?= htmlspecialchars($att['url']) ?>','<?= htmlspecialchars($att['name']) ?>');return false;"
                               style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#f0f2f5;border-radius:8px;font-size:12px;color:#1a1a2e;text-decoration:none;border:1px solid #e0e0e0;">
                                &#128196; <?= htmlspecialchars($att['name']) ?>
                            </a>
                        <?php endif; ?>
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
<!-- Image lightbox -->
<div id="imgModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:2000;align-items:center;justify-content:center;" onclick="this.style.display='none'">
    <img id="imgModalSrc" src="" style="max-width:92vw;max-height:90vh;border-radius:10px;box-shadow:0 8px 40px rgba(0,0,0,0.5);">
</div>
<!-- Document viewer modal -->
<div id="docModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:2001;flex-direction:column;align-items:center;justify-content:flex-start;padding-top:60px;">
    <div style="position:absolute;top:15px;right:20px;z-index:2002;">
        <button onclick="closeDocModal()" style="background:none;border:none;color:#fff;font-size:32px;cursor:pointer;padding:0 10px;">&times;</button>
    </div>
    <div id="docTitle" style="color:#fff;font-size:16px;margin-bottom:15px;font-weight:600;"></div>
    <iframe id="docViewer" src="" style="width:90vw;height:80vh;border:1px solid #444;border-radius:8px;background:#fff;"></iframe>
    <div style="margin-top:15px;">
        <a id="docDownloadLink" href="" target="_blank" rel="noopener" style="color:#fff;text-decoration:underline;font-size:14px;">Open in new tab / Download</a>
    </div>
</div>
<script>
function showImg(url) {
    document.getElementById('imgModalSrc').src = url;
    document.getElementById('imgModal').style.display = 'flex';
}
function showDoc(url, name) {
    document.getElementById('docTitle').textContent = name;
    document.getElementById('docViewer').src = url;
    document.getElementById('docDownloadLink').href = url;
    document.getElementById('docModal').style.display = 'flex';
}
function closeDocModal() {
    document.getElementById('docModal').style.display = 'none';
    document.getElementById('docViewer').src = '';
}
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
