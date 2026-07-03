<?php
include 'config.php';
requireLogin();
$u = $_SESSION['user'];
$unreadCount = $notifications->countDocuments(['roll'=>$u['roll'],'read'=>false]);
$error = '';
$success = '';

if (isset($_POST['save'])) {
    $roll = $_SESSION['user']['roll'];
    $reg  = $_SESSION['user']['reg'];
    $sem  = (int)$_POST['sem'];
    $mentorId = trim($_POST['mentor_id']);
    
    // Collect subjects data
    $names    = $_POST['subject_name'];
    $codes    = $_POST['subject_code'];
    $credits  = $_POST['credits'];
    $internal = $_POST['internal'];
    
    $subjectsData = [];
    foreach ($names as $i => $name) {
        if (empty(trim($name))) continue;
        $subjectsData[] = [
            'name'   => trim($name),
            'code'   => trim($codes[$i]),
            'credits' => (int)$credits[$i],
            'internal' => $internal[$i],
        ];
    }
    
    if (empty($subjectsData)) {
        $error = 'Please add at least one subject.';
    } else {
        // Create approval request instead of direct save
        $approvalData = [
            'student_roll' => $roll,
            'student_name' => $_SESSION['user']['name'],
            'type' => 'Semester Registration',
            'semester' => $sem,
            'reg' => $reg,
            'mentor_id' => $mentorId,
            'subjects' => $subjectsData,
            'subject_count' => count($subjectsData),
            'message' => 'Request to register Semester ' . $sem . ' with ' . count($subjectsData) . ' subjects',
            'status' => 'pending',
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ];
        
        $result = $approvals->insertOne($approvalData);
        
        // Always update mentor_id in user profile to ensure mentor can see the approval
        if (!empty($mentorId)) {
            $users->updateOne(['roll' => $roll], ['$set' => ['mentor_id' => $mentorId]]);
            $_SESSION['user']['mentor_id'] = $mentorId;
        }
        
        // Create notification for mentor
        if (!empty($mentorId)) {
            $notifications->insertOne([
                'mentor_id' => $mentorId,
                'message' => '📝 New semester registration request from ' . $_SESSION['user']['name'] . ' (' . $roll . ') - Semester ' . $sem,
                'read' => false,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);
        }
        
        // Create notification for student to confirm their request was submitted
        $notifications->insertOne([
            'roll' => $roll,
            'message' => '✅ Your semester ' . $sem . ' registration request has been submitted and is pending mentor approval.',
            'read' => false,
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        
        $success = 'Your semester registration request has been submitted for approval. You will be notified once your mentor reviews it.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Add Semester</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<nav class="navbar">
<a href="dashboard.php" class="nav-brand">
    <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo">
    SGI
</a>
    <div class="nav-links">
        <a href="academics.php">&#8592; Back</a>
        <a href="update_profile.php">Profile</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="print_select.php">Print</a>
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
                &#128276;<?php if($unreadCount>0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <span style="display:flex;gap:10px;"><a href="#" onclick="markAll(event)">Mark read</a><a href="#" onclick="clearAll(event)">Clear all</a></span></div>
                <div class="notif-list-scroll" id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
    <div class="form-box">
        <h2>Add Semester Details</h2>
        <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success" style="background:#d4edda;color:#155724;padding:12px;border-radius:8px;margin-bottom:16px;"><?= $success ?></p><?php endif; ?>
        <?php if (!$success): ?>
        <form method="POST" id="sem-form">
            <input type="hidden" name="save" value="1">
            <label>Semester Number</label>
            <input type="number" name="sem" min="1" max="8" required>
            <label>Mentor ID</label>
            <input type="text" name="mentor_id" placeholder="Mentor ID" required>
            <h3>Subjects</h3>
            <div id="subject-list">
                <div class="subject-row">
                    <input type="text"   name="subject_name[]" placeholder="Subject Name" required>
                    <input type="text"   name="subject_code[]" placeholder="Subject Code" required>
                    <input type="number" name="credits[]"      placeholder="Credits" min="0" max="6" required>
                    <select name="internal[]" required>
                        <option value="">Internal</option>
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>
                    <button type="button" class="btn-remove" onclick="removeRow(this)">✕</button>
                </div>
            </div>
            <button type="button" class="btn-add-subject" onclick="addRow()">+ Add Subject</button>
            <button type="button" class="btn-primary" style="margin-top:20px;" onclick="checkMentorId()">Save Semester</button>
            <a href="dashboard.php" class="btn-secondary">Cancel</a>
        </form>
        <?php else: ?>
        <div style="text-align:center;margin-top:20px;">
            <a href="add_semester.php" class="btn-primary" style="margin:5px;">Add Another Semester</a>
            <a href="student_approvals.php" class="btn-secondary" style="margin:5px;">View Approval Status</a>
            <a href="academics.php" class="btn-secondary" style="margin:5px;">Back to Academics</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="mentor-popup" class="popup" style="display:none;">
    <div class="popup-box">
        <p>The Mentor ID you entered will also update your profile Mentor ID card on the dashboard. Do you want to continue?</p>
        <button onclick="document.getElementById('sem-form').submit()" style="background:#e94560;">Yes, Update</button>
        <button onclick="document.getElementById('mentor-popup').style.display='none'" style="background:#eee;color:#333;margin-left:10px;">Cancel</button>
    </div>
</div>
<script>
function addRow() {
    const list = document.getElementById('subject-list');
    const row  = document.createElement('div');
    row.className = 'subject-row';
    row.innerHTML = `
        <input type="text"   name="subject_name[]" placeholder="Subject Name" required>
        <input type="text"   name="subject_code[]" placeholder="Subject Code" required>
        <input type="number" name="credits[]"      placeholder="Credits" min="0" max="6" required>
        <select name="internal[]" required>
            <option value="">Internal?</option>
            <option value="yes">Yes</option>
            <option value="no">No</option>
        </select>
        <button type="button" class="btn-remove" onclick="removeRow(this)">✕</button>
    `;
    list.appendChild(row);
}
function checkMentorId() {
    const currentMentorId = '<?= htmlspecialchars($_SESSION["user"]["mentor_id"] ?? "") ?>';
    const enteredMentorId = document.querySelector('[name=mentor_id]').value.trim();
    if (enteredMentorId && enteredMentorId !== currentMentorId) {
        document.getElementById('mentor-popup').style.display = 'flex';
    } else {
        document.getElementById('sem-form').submit();
    }
}
function removeRow(btn) {
    const list = document.getElementById('subject-list');
    if (list.children.length > 1) btn.parentElement.remove();
}
</script>
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
function clearAll(e) {
    e.preventDefault();
    fetch('notifications.php?delete_all=1');
    document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>';
    const badge = document.querySelector('.notif-badge');
    if(badge) badge.remove();
}
document.addEventListener('click', e => {
    const btn = document.getElementById('bellBtn');
    const drop = document.getElementById('notifDrop');
    if (btn && drop && !btn.contains(e.target) && !drop.contains(e.target))
        drop.classList.remove('open');
});
</script>
</body>
</html>


