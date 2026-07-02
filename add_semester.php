<?php
include 'config.php';
requireLogin();
$u = $_SESSION['user'];
$unreadCount = $notifications->countDocuments(['roll'=>$u['roll'],'read'=>false]);
$error = '';
if (isset($_POST['save'])) {
    $roll = $_SESSION['user']['roll'];
    $reg  = $_SESSION['user']['reg'];
    $sem  = (int)$_POST['sem'];

    $result = $semesters->insertOne(['roll' => $roll, 'reg' => $reg, 'sem' => $sem, 'mentor_id' => $_POST['mentor_id']]);
    $sem_id = (string)$result->getInsertedId();

    if (!empty($_POST['mentor_id'])) {
        $users->updateOne(['roll' => $roll], ['$set' => ['mentor_id' => $_POST['mentor_id']]]);
        $_SESSION['user']['mentor_id'] = $_POST['mentor_id'];
    }

    $names    = $_POST['subject_name'];
    $codes    = $_POST['subject_code'];
    $credits  = $_POST['credits'];
    $internal = $_POST['internal'];

    foreach ($names as $i => $name) {
        if (empty(trim($name))) continue;
        $subjects->insertOne([
            'sem_id'       => $sem_id,
            'roll'         => $roll,
            'subject_name' => $name,
            'subject_code' => $codes[$i],
            'credits'      => (int)$credits[$i],
            'internal'     => $internal[$i],
        ]);
    }
    header("Location: dashboard.php");
    exit;
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
                <div class="notif-dropdown-header">Notifications <a href="#" onclick="markAll(event)">Mark all read</a></div>
                <div id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
    <div class="form-box">
        <h2>Add Semester Details</h2>
        <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
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
                    <button type="button" class="btn-remove" onclick="removeRow(this)">?</button>
                </div>
            </div>
            <button type="button" class="btn-add-subject" onclick="addRow()">+ Add Subject</button>
            <button type="button" class="btn-primary" style="margin-top:20px;" onclick="checkMentorId()">Save Semester</button>
            <a href="dashboard.php" class="btn-secondary">Cancel</a>
        </form>
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
        <button type="button" class="btn-remove" onclick="removeRow(this)">?</button>
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
document.addEventListener('click', e => {
    const btn = document.getElementById('bellBtn');
    const drop = document.getElementById('notifDrop');
    if (btn && drop && !btn.contains(e.target) && !drop.contains(e.target))
        drop.classList.remove('open');
});
</script>
</body>
</html>


