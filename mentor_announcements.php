<?php
include 'config.php';
if (!isset($_SESSION['mentor'])) { header("Location: mentor_login.php"); exit; }
$m = $_SESSION['mentor'];

// Post announcement
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['post_announcement'])) {
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body']  ?? '');
    $type  = trim($_POST['type_custom'] ?? '') ?: (trim($_POST['type_select'] ?? '') ?: 'general');
    $type  = ($type === '__custom') ? 'general' : $type;
    if ($title && $body) {
        $attachments = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['tmp_name'] as $i => $tmp) {
                if (!$tmp || $_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $origName = $_FILES['attachments']['name'][$i];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $resourceType = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' : 'raw';
                $url = uploadToCloudinary($tmp, 'sgi/announcements', $resourceType);
                $attachments[] = ['name' => $origName, 'url' => $url, 'type' => $resourceType];
            }
        }
        $announcements->insertOne([
            'mentor_id'   => $m['mentor_id'],
            'mentor_name' => $m['name'],
            'title'       => $title,
            'body'        => $body,
            'type'        => $type,
            'attachments' => $attachments,
            'created_at'  => new MongoDB\BSON\UTCDateTime()
        ]);
        $students = $users->find(['mentor_id' => $m['mentor_id']]);
        foreach ($students as $st) {
            $notifications->insertOne([
                'roll'    => $st['roll'],
                'message' => "\xF0\x9F\x93\xA2 New announcement: $title",
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
                <div class="notif-dropdown-header">Notifications <a href="#" onclick="markAll(event)">Mark all read</a></div>
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
            <form method="POST" enctype="multipart/form-data">
                <label>Title</label>
                <input type="text" name="title" placeholder="Announcement title…" required>
                <label>Message</label>
                <textarea name="body" rows="4" placeholder="Write your announcement…" required></textarea>
                <label>Type (select or create new)</label>
                <select name="type_select" id="annTypeSelect" onchange="document.getElementById('annTypeCustom').style.display=this.value==='__custom'?'block':'none'">
                    <option value="urgent">Urgent</option>
                    <option value="info">Info</option>
                    <option value="general" selected>General</option>
                    <option value="__custom">+ Custom type…</option>
                </select>
                <input type="text" name="type_custom" id="annTypeCustom" placeholder="e.g. Reminder, Warning…" style="margin-top:6px;display:none;">
                <label style="margin-top:14px;">Attachments (PDF, Word, Images — multiple allowed)</label>
                <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp" style="background:#f9f9f9;padding:10px;">
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
                    <?php if(!empty($a['attachments'])): ?>
                    <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;">
                        <?php foreach($a['attachments'] as $att): ?>
                            <?php $isImg = ($att['type']==='image'); ?>
                            <?php if($isImg): ?>
                                <a href="#" onclick="mentorShowImg('<?= htmlspecialchars($att['url']) ?>');return false;"
                                   style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#f0f2f5;border-radius:8px;font-size:12px;color:#1a1a2e;text-decoration:none;border:1px solid #e0e0e0;">
                                    &#128444; <?= htmlspecialchars($att['name']) ?>
                                </a>
                            <?php else: ?>
                                <a href="#" onclick="mentorShowDoc('<?= htmlspecialchars($att['url']) ?>','<?= htmlspecialchars($att['name']) ?>');return false;"
                                   style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#f0f2f5;border-radius:8px;font-size:12px;color:#1a1a2e;text-decoration:none;border:1px solid #e0e0e0;">
                                    &#128196; <?= htmlspecialchars($att['name']) ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
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
<!-- Image lightbox -->
<div id="mentorImgModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:2000;align-items:center;justify-content:center;" onclick="this.style.display='none'">
    <img id="mentorImgModalSrc" src="" style="max-width:92vw;max-height:90vh;border-radius:10px;box-shadow:0 8px 40px rgba(0,0,0,0.5);">
</div>
<!-- Document viewer modal -->
<div id="mentorDocModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:2001;flex-direction:column;align-items:center;justify-content:flex-start;padding-top:60px;">
    <div style="position:absolute;top:15px;right:20px;z-index:2002;">
        <button onclick="mentorCloseDocModal()" style="background:none;border:none;color:#fff;font-size:32px;cursor:pointer;padding:0 10px;">&times;</button>
    </div>
    <div id="mentorDocTitle" style="color:#fff;font-size:16px;margin-bottom:15px;font-weight:600;"></div>
    <iframe id="mentorDocViewer" src="" style="width:90vw;height:80vh;border:1px solid #444;border-radius:8px;background:#fff;"></iframe>
    <div style="margin-top:15px;">
        <a id="mentorDocDownloadLink" href="" target="_blank" rel="noopener" style="color:#fff;text-decoration:underline;font-size:14px;">Open in new tab / Download</a>
    </div>
</div>
<script>
function mentorShowImg(url) {
    document.getElementById('mentorImgModalSrc').src = url;
    document.getElementById('mentorImgModal').style.display = 'flex';
}
function mentorShowDoc(url, name) {
    document.getElementById('mentorDocTitle').textContent = name;
    document.getElementById('mentorDocViewer').src = url;
    document.getElementById('mentorDocDownloadLink').href = url;
    document.getElementById('mentorDocModal').style.display = 'flex';
}
function mentorCloseDocModal() {
    document.getElementById('mentorDocModal').style.display = 'none';
    document.getElementById('mentorDocViewer').src = '';
}
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
