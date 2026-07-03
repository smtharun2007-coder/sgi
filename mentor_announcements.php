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

// Fetch all previously used announcement types by this mentor (only custom types)
$annTypes = [];
$typeCursor = $announcements->find(
    ['mentor_id' => $m['mentor_id']],
    ['projection' => ['type'=>1,'color'=>1], 'sort' => ['created_at'=>-1]]
);
foreach ($typeCursor as $ann) {
    $t = $ann['type'] ?? '';
    // Only add custom types (not the default ones)
    if ($t && $t !== 'urgent' && $t !== 'info' && $t !== 'general' && !isset($annTypes[$t])) {
        $annTypes[$t] = $ann['color'] ?? '#e94560';
    }
}

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
                <div class="notif-dropdown-header">Notifications <span style="display:flex;gap:10px;"><a href="#" onclick="markAll(event)">Mark read</a><a href="#" onclick="clearAll(event)">Clear all</a></span></div>
                <div class="notif-list-scroll" id="notifList"><div class="notif-empty">Loading…</div></div>
            </div>
        </div>
        <a href="mentor_logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">

    <div class="page-tabs">
        <a href="#post" class="page-tab active">📢 Post Announcement</a>
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
                <label>Type</label>
                <select name="type_select" id="annTypeSelect" onchange="handleTypeChange()">
                    <?php foreach($annTypes as $typeName => $typeColor): ?>
                    <option value="<?= htmlspecialchars($typeName) ?>" data-color="<?= htmlspecialchars($typeColor) ?>"><?= ucfirst(htmlspecialchars($typeName)) ?></option>
                    <?php endforeach; ?>
                    <option value="__custom">+ Create new type…</option>
                </select>
                <!-- Hidden field to store the color for existing types -->
                <input type="hidden" name="color" id="selectedColor" value="#e94560">
                <div id="annTypeCustomContainer" style="margin-top:6px;display:none;">
                    <input type="text" name="type_custom" id="annTypeCustom" placeholder="e.g. Reminder, Warning…" style="width:100%;padding:10px;">
                    <label style="margin-top:8px;display:block;">Color for this new type</label>
                    <input type="color" id="typeColorCustom" value="#e94560" style="width:60px;height:40px;padding:0;border:none;cursor:pointer;" onchange="document.getElementById('selectedColor').value=this.value;">
                </div>
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
                   onclick="event.preventDefault();customConfirm('Delete this announcement?', function(){window.location.href='mentor_announcements.php?delete_ann=<?= (string)$a['_id'] ?>';});return false;"
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
function handleTypeChange() {
    const select = document.getElementById('annTypeSelect');
    const customContainer = document.getElementById('annTypeCustomContainer');
    const hiddenColor = document.getElementById('selectedColor');
    const colorPicker = document.getElementById('typeColorCustom');
    
    if (select.value === '__custom') {
        // Show custom type input and color picker for creating new type
        customContainer.style.display = 'block';
        // Sync color picker with hidden field
        colorPicker.value = hiddenColor.value;
    } else {
        // Hide custom type container - get color from selected option
        customContainer.style.display = 'none';
        const selectedOption = select.options[select.selectedIndex];
        const color = selectedOption.getAttribute('data-color');
        if (color) {
            hiddenColor.value = color;
            console.log('Type selected:', selectedOption.value, 'Color:', color);
        } else {
            console.log('No data-color found for:', selectedOption.value);
        }
    }
}

function toggleStudentCheckboxes(checkbox) {
    const checkboxes = document.querySelectorAll('input[name="students[]"]');
    checkboxes.forEach(cb => {
        if (cb.value === 'all') return; // Don't uncheck the "All" checkbox itself
        cb.checked = checkbox.checked;
    });
}
// Initialize type selector on page load
document.addEventListener('DOMContentLoaded', function() {
    handleTypeChange();  // Check initial state
});

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

// Custom Toast Notification System
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;top:80px;right:20px;background:${type==='success'?'#28a745':type==='error'?'#dc3545':type==='warning'?'#ffc107':'#17a2b8'};color:#fff;padding:14px 24px;border-radius:12px;font-size:14px;font-weight:600;z-index:10000;box-shadow:0 8px 30px rgba(0,0,0,0.2);animation:toastSlideIn 0.3s ease;max-width:350px;display:flex;align-items:center;gap:10px;`;
    const icons = {success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};
    toast.innerHTML = `<span>${icons[type]||''}</span><span>${message}</span>`;
    document.body.appendChild(toast);
    if (!document.getElementById('toastStyles')) {
        const style = document.createElement('style');
        style.id = 'toastStyles';
        style.textContent = `@keyframes toastSlideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}@keyframes toastSlideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(100%);opacity:0}}`;
        document.head.appendChild(style);
    }
    setTimeout(() => { toast.style.animation = 'toastSlideOut 0.3s ease'; setTimeout(() => toast.remove(), 300); }, 3000);
}

// Custom Confirm Dialog
function customConfirm(message, onConfirm, onCancel) {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);';
    const modal = document.createElement('div');
    modal.style.cssText = 'background:#fff;border-radius:20px;padding:28px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:modalSlide 0.3s ease;';
    modal.innerHTML = `<div style="text-align:center;"><div style="font-size:48px;margin-bottom:16px;">🤔</div><h3 style="margin-bottom:12px;color:#1a1a2e;font-size:18px;">Confirm Action</h3><p style="color:#666;margin-bottom:24px;font-size:14px;line-height:1.5;">${message}</p><div style="display:flex;gap:12px;justify-content:center;"><button id="confirmCancel" style="flex:1;padding:12px;border-radius:10px;border:none;background:#e9ecef;color:#555;font-size:14px;font-weight:600;cursor:pointer;">Cancel</button><button id="confirmOk" style="flex:1;padding:12px;border-radius:10px;border:none;background:linear-gradient(135deg,#1a1a2e,#e94560);color:#fff;font-size:14px;font-weight:600;cursor:pointer;">Confirm</button></div></div>`;
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    if (!document.getElementById('modalStyles')) {
        const style = document.createElement('style');
        style.id = 'modalStyles';
        style.textContent = `@keyframes modalSlide{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}`;
        document.head.appendChild(style);
    }
    document.getElementById('confirmOk').onclick = () => { overlay.remove(); if(onConfirm) onConfirm(); };
    document.getElementById('confirmCancel').onclick = () => { overlay.remove(); if(onCancel) onCancel(); };
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
function clearAll(e) {
    e.preventDefault();
    fetch('notifications.php?delete_all=1&mentor=1');
    document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>';
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
