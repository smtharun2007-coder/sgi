<?php
include 'config.php';
requireLogin();
$u = $_SESSION['user'];
$unreadCount = $notifications->countDocuments(['roll'=>$u['roll'],'read'=>false]);
$success = '';

if (isset($_POST['update'])) {
    $error_up = '';
    $photo = $u['photo'];
    if (!empty($_FILES['photo']['name'])) {
        if ($_FILES['photo']['size'] > 2 * 1024 * 1024) { $error_up = "Profile photo must be = 2 MB."; }
        else { $photo = uploadToCloudinary($_FILES['photo']['tmp_name'], 'sgi/students'); }
    }
    $signature = $u['signature'];
    if (!$error_up && !empty($_FILES['signature']['name'])) {
        if ($_FILES['signature']['size'] > 1 * 1024 * 1024) { $error_up = "Signature must be = 1 MB."; }
        else { $signature = uploadToCloudinary($_FILES['signature']['tmp_name'], 'sgi/signatures'); }
    }
    if (!$error_up) {
    $update = [
        'name'      => $_POST['name'],
        'dob'       => $_POST['dob'],
        'father'    => $_POST['father'],
        'mother'    => $_POST['mother'],
        'email'     => $_POST['email'],
        'dept'      => $_POST['dept'],
        'batch_no'  => $_POST['batch_no'],
        'year_from' => (int)$_POST['year_from'],
        'year_to'   => (int)$_POST['year_to'],
        'class'     => $_POST['class'],
        'phone'     => $_POST['phone'],
        'photo'     => $photo,
        'signature' => $signature,
    ];
    $users->updateOne(['roll' => $u['roll']], ['$set' => $update]);
    $_SESSION['user'] = array_merge($u, $update);
    $u = $_SESSION['user'];
    $success = "Profile updated successfully.";
    } // end !$error_up
    else { $success = ''; }
}

$pwdError = '';
$pwdSuccess = '';
if (isset($_POST['change_password'])) {
    $currentUser = $users->findOne(['roll' => $u['roll']]);
    if (!password_verify($_POST['current_password'], $currentUser['password'])) {
        $pwdError = "Current password is incorrect.";
    } elseif ($_POST['new_password'] !== $_POST['confirm_new_password']) {
        $pwdError = "New passwords do not match.";
    } elseif (strlen($_POST['new_password']) < 6) {
        $pwdError = "New password must be at least 6 characters.";
    } else {
        $users->updateOne(['roll' => $u['roll']], ['$set' => ['password' => password_hash($_POST['new_password'], PASSWORD_DEFAULT)]]);
        $pwdSuccess = "Password changed successfully.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Update Profile</title>
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
        <a href="dashboard.php">Home</a>
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
    <h2>Update Profile</h2>
    <?php if (!empty($error_up)): ?><p class="error"><?= $error_up ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <label>Roll Number (cannot be changed)</label>
        <input type="text" value="<?= htmlspecialchars($u['roll']) ?>" readonly class="locked">
        <label>Integrated Number (cannot be changed)</label>
        <input type="text" value="<?= htmlspecialchars($u['reg']) ?>" readonly class="locked">
        <input type="text"   name="name"      placeholder="Full Name"    value="<?= htmlspecialchars($u['name']) ?>" required>
        <label>Date of Birth</label>
        <input type="date"   name="dob"       value="<?= $u['dob'] ?>" required>
        <input type="text"   name="father"    placeholder="Father's Name" value="<?= htmlspecialchars($u['father']) ?>">
        <input type="text"   name="mother"    placeholder="Mother's Name" value="<?= htmlspecialchars($u['mother']) ?>">
        <input type="email"  name="email"     placeholder="Email"         value="<?= htmlspecialchars($u['email']) ?>" required>
        <input type="text"   name="dept"      placeholder="Department"    value="<?= htmlspecialchars($u['dept']) ?>">
        <input type="text"   name="batch_no"  placeholder="Batch No"      value="<?= htmlspecialchars($u['batch_no'] ?? '') ?>">
        <input type="number" name="year_from" placeholder="Year From"     value="<?= $u['year_from'] ?>">
        <input type="number" name="year_to"   placeholder="Year To"       value="<?= $u['year_to'] ?>">
        <input type="text"   name="class"     placeholder="Class"         value="<?= htmlspecialchars($u['class']) ?>">
        <input type="tel"    name="phone"     placeholder="Phone"         value="<?= htmlspecialchars($u['phone']) ?>">
        <input type="text"   name="mentor_id"  placeholder="Mentor ID"     value="<?= htmlspecialchars($u['mentor_id'] ?? '') ?>" readonly class="locked">
        <?php if (!empty($u['photo'])): ?>
            <label>Current Photo</label>
            <img src="<?= htmlspecialchars(imgUrl($u['photo'])) ?>" style="width:100px;height:100px;object-fit:cover;border:1px solid #ddd;border-radius:6px;padding:4px;display:block;margin:6px 0;">
        <?php endif; ?>
        <label>Update Photo (optional)</label>
        <input type="file" name="photo" accept="image/*">
        <?php if (!empty($u['signature'])): ?>
            <label>Current Signature</label>
            <img src="<?= htmlspecialchars(imgUrl($u['signature'])) ?>" style="width:160px;height:60px;object-fit:contain;border:1px solid #ddd;border-radius:6px;padding:4px;display:block;margin:6px 0;">
        <?php endif; ?>
        <label>Update Signature (optional)</label>
        <input type="file" name="signature" accept="image/*">
        <button type="submit" name="update" class="btn-primary">Save Changes</button>
        <a href="dashboard.php" class="btn-secondary">Cancel</a>
    </form>

    <hr style="margin:30px 0;">
    <h2>Change Password</h2>
    <?php if ($pwdError): ?><p class="error"><?= $pwdError ?></p><?php endif; ?>
    <?php if ($pwdSuccess): ?><p class="success"><?= $pwdSuccess ?></p><?php endif; ?>
    <form method="POST">
        <label>Current Password</label>
        <input type="password" name="current_password" required>
        <label>New Password</label>
        <input type="password" name="new_password" required>
        <label>Confirm New Password</label>
        <input type="password" name="confirm_new_password" required>
        <button type="submit" name="change_password" class="btn-primary">Change Password</button>
    </form>
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
    const btn = document.getElementById('bellBtn');
    const drop = document.getElementById('notifDrop');
    if (btn && drop && !btn.contains(e.target) && !drop.contains(e.target))
        drop.classList.remove('open');
});
</script>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>


