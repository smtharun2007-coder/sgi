<?php
include 'config.php';
if (!isset($_SESSION['mentor'])) { header("Location: mentor_login.php"); exit; }
if (isset($_SESSION['last_mentor_activity']) && (time() - $_SESSION['last_mentor_activity'] > 1800)) {
    unset($_SESSION['mentor']);
    header("Location: mentor_login.php?timeout=1");
    exit;
}
$_SESSION['last_mentor_activity'] = time();
$m = $_SESSION['mentor'];

$success = '';
$error_up = '';

if (isset($_POST['update'])) {
    $photo = $m['photo'];
    if (!empty($_FILES['photo']['name'])) {
        if ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
            $error_up = "Profile photo must be ≤ 2 MB.";
        } else {
            $photo = uploadToCloudinary($_FILES['photo']['tmp_name'], 'sgi/mentors');
        }
    }
    if (!$error_up) {
        $update = [
            'name'  => $_POST['name'],
            'email' => $_POST['email'],
            'dept'  => $_POST['dept'],
            'phone' => $_POST['phone'],
            'photo' => $photo,
        ];
        $mentors->updateOne(['mentor_id' => $m['mentor_id']], ['$set' => $update]);
        $_SESSION['mentor'] = array_merge($m, $update);
        $m = $_SESSION['mentor'];
        $success = "Profile updated successfully.";
    }
}

$pwdError = '';
$pwdSuccess = '';
if (isset($_POST['change_password'])) {
    $currentMentor = $mentors->findOne(['mentor_id' => $m['mentor_id']]);
    if (!password_verify($_POST['current_password'], $currentMentor['password'])) {
        $pwdError = "Current password is incorrect.";
    } elseif ($_POST['new_password'] !== $_POST['confirm_new_password']) {
        $pwdError = "New passwords do not match.";
    } elseif (strlen($_POST['new_password']) < 6) {
        $pwdError = "New password must be at least 6 characters.";
    } else {
        $mentors->updateOne(['mentor_id' => $m['mentor_id']], ['$set' => ['password' => password_hash($_POST['new_password'], PASSWORD_DEFAULT)]]);
        $pwdSuccess = "Password changed successfully.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Mentor Profile</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .mentor-navbar { background: linear-gradient(135deg, #1a1a2e, #8e44ad); }
    </style>
</head>
<body>
<nav class="navbar mentor-navbar">
    <span class="nav-brand">SGI <span style="font-size:13px;opacity:0.7;font-weight:400;">Mentor</span></span>
    <div class="nav-links">
        <a href="mentor_dashboard.php">Home</a>
        <a href="mentor_update_profile.php">Update Profile</a>
        <a href="mentor_about.php">About</a>
        <a href="mentor_contact.php">Contact</a>
        <a href="mentor_logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
<div class="form-box">
    <h2>Update Profile</h2>
    <?php if ($error_up): ?><p class="error"><?= $error_up ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <label>Mentor ID (cannot be changed)</label>
        <input type="text" value="<?= htmlspecialchars($m['mentor_id']) ?>" readonly class="locked">
        <input type="text"  name="name"  placeholder="Full Name"   value="<?= htmlspecialchars($m['name']) ?>"  required>
        <input type="email" name="email" placeholder="Email"        value="<?= htmlspecialchars($m['email']) ?>" required>
        <input type="text"  name="dept"  placeholder="Department"  value="<?= htmlspecialchars($m['dept']) ?>">
        <input type="tel"   name="phone" placeholder="Phone Number" value="<?= htmlspecialchars($m['phone']) ?>">
        <?php if (!empty($m['photo'])): ?>
            <label>Current Photo</label>
            <img src="<?= htmlspecialchars(imgUrl($m['photo'])) ?>" style="width:100px;height:100px;object-fit:cover;border:1px solid #ddd;border-radius:6px;padding:4px;display:block;margin:6px 0;">
        <?php endif; ?>
        <label>Update Photo (optional, ≤ 200 KB)</label>
        <input type="file" name="photo" accept="image/*">
        <button type="submit" name="update" class="btn-primary">Save Changes</button>
        <a href="mentor_dashboard.php" class="btn-secondary">Cancel</a>
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
</body>
</html>
