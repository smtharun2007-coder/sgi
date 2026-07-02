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
    $color = trim($_POST['color'] ?? '#e94560');
    $recipient = $_POST['recipient'] ?? 'all'; // 'all' or 'own'
    if ($title && $body) {
        $attachments = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['tmp_name'] as $i => $tmp) {
                if (!$tmp || $_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $origName = $_FILES['attachments']['name'][$i];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $resourceType = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' : 'raw';
                // Generate unique public_id to preserve filename
                $publicId = 'ann_' . time() . '_' . pathinfo($origName, PATHINFO_FILENAME);
                $url = uploadToCloudinary($tmp, 'sgi/announcements', $resourceType, $publicId);
                $attachments[] = ['name' => $origName, 'url' => $url, 'type' => $resourceType];
            }
        }
        $announcements->insertOne([
            'mentor_id'   => $m['mentor_id'],
            'mentor_name' => $m['name'],
            'title'       => $title,
            'body'        => $body,
            'type'        => $type,
            'color'       => $color,
            'recipient'   => $recipient,
            'attachments' => $attachments,
            'created_at'  => new MongoDB\BSON\UTCDateTime()
        ]);
        // Send notifications based on selected students
        $selectedStudents = $_POST['students'] ?? [];
        if (in_array('all', $selectedStudents)) {
            // Send to all students under this mentor
            $students = $users->find(['mentor_id' => $m['mentor_id']]);
        } else {
            // Send only to selected students
            $studentRolls = array_filter($selectedStudents, fn($r) => $r !== 'all');
            if (!empty($studentRolls)) {
                $students = $users->find(['roll' => ['$in' => $studentRolls]]);
            } else {
                $students = [];
            }
        }
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
                <div id="annTypeCustomContainer" style="margin-top:6px;display:none;">
                    <input type="text" name="type_custom" id="annTypeCustom" placeholder="e.g. Reminder, Warning…" style="width:100%;padding:10px;">
                    <label style="margin-top:8px;display:block;">Color for this type</label>
                    <input type="color" name="type_color" value="#e94560" style="width:60px;height:40px;padding:0;border:none;cursor:pointer;">
                </div>
                <label style="margin-top:8px;display:block;">Type Color</label>
                <input type="color" name="color" value="#e94560" id="typeColorPicker" style="width:60px;height:40px;padding:0;border:none;cursor:pointer;">
                <label style="margin-top:14px;">Select Students</label>
                <div style="background:#f9f9f9;padding:12px;border-radius:6px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #e0e0e0;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                            <input type="checkbox" name="students[]" value="all" checked onchange="toggleStudentCheckboxes(this)" style="width:16px;height:16px;cursor:pointer;">
                            <strong style="font-size:13px;">All Students</strong>
                            <span style="color:#888;font-size:12px;">(<?= count(iterator_to_array($users->find(['mentor_id' => $m['mentor_id']]))) ?>)</span>
                        </label>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;max-height:180px;overflow-y:auto;">
                        <?php
                        $myStudents = $users->find(['mentor_id' => $m['mentor_id']]);
                        foreach ($myStudents as $st):
                        ?>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:6px 8px;border-radius:4px;transition:background 0.2s;" onmouseenter="this.style.background='#f0f0f0'" onmouseleave="this.style.background='transparent'">
                            <input type="checkbox" name="students[]" value="<?= htmlspecialchars($st['roll']) ?>" style="width:15px;height:15px;cursor:pointer;">
                            <span style="font-size:12px;"><?= htmlspecialchars($st['name']) ?></span>
                            <span style="font-size:11px;color:#888;"><?= htmlspecialchars($st['roll']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <label style="margin-top:14px;">Attachments (PDF, Word, Images — multiple allowed)</label>
                <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp" style="background:#f9f9f9;padding:10px;">
                <button type="submit" name="post_announcement" class="btn-primary" style="margin-top:16px;">Post Announcement</button>
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
                $color = $a['color'] ?? '#e94560';
            ?>
            <div class="announce-card" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;border-left:4px solid <?= $color ?>;background:#fff;padding:16px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:16px;">
                <div style="flex:1;">
                    <div class="announce-title"><?= htmlspecialchars($a['title']) ?> <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;color:#fff;background:<?= $color ?>;"><?= ucfirst($typeClass) ?></span></div>
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
function toggleStudentCheckboxes(checkbox) {
    const checkboxes = document.querySelectorAll('input[name="students[]"]');
    checkboxes.forEach(cb => {
        if (cb.value === 'all') return; // Don't uncheck the "All" checkbox itself
        cb.checked = checkbox.checked;
    });
}
// Also handle individual student checkbox clicks
document.addEventListener('DOMContentLoaded', function() {
    const studentCheckboxes = document.querySelectorAll('input[name="students[]"]');
    studentCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const allCheckbox = document.querySelector('input[name="students[]"][value="all"]');
            // If any individual checkbox is unchecked, uncheck "All"
            if (this.value !== 'all' && !this.checked) {
                allCheckbox.checked = false;
            }
            // If all individual checkboxes are checked, check "All"
            const individualCheckboxes = document.querySelectorAll('input[name="students[]"]:not([value="all"])');
            const allIndividualChecked = Array.from(individualCheckboxes).every(cb => cb.checked);
            if (allIndividualChecked && individualCheckboxes.length > 0) {
                allCheckbox.checked = true;
            } else if (this.value === 'all' && !this.checked) {
                // If "All" is unchecked, keep individual checkboxes as they are
            }
        });
    });
});
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
