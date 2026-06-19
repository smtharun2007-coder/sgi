<?php
include 'config.php';
requireLogin();
$u = $_SESSION['user'];
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
    <link rel="stylesheet" href="/css/style.css?v=2">
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
        <a href="update_profile.php">Update Profile</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="print_select.php">Print</a>
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
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>


