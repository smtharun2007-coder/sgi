<?php
include 'config.php';
if (isset($_SESSION['mentor'])) { header("Location: mentor_dashboard.php"); exit; }
$error = '';
if (isset($_POST['signup'])) {
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error = "Passwords do not match.";
    } elseif (strlen($_POST['password']) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $existing = $mentors->findOne(['mentor_id' => $_POST['mentor_id']]);
        if ($existing) {
            $error = "Mentor ID already registered.";
        } else {
            $existingEmail = $mentors->findOne(['email' => $_POST['email']]);
            if ($existingEmail) {
                $error = "Email already registered.";
            } else {
                $photo = '';
                if (!empty($_FILES['photo']['name'])) {
                    if ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
                        $error = "Profile photo must be = 2 MB.";
                    } else {
                        $photo = uploadToCloudinary($_FILES['photo']['tmp_name'], 'sgi/mentors');
                    }
                }
                if (!$error) {
                    $mentors->insertOne([
                        'name'       => $_POST['name'],
                        'mentor_id'  => $_POST['mentor_id'],
                        'email'      => $_POST['email'],
                        'dept'       => $_POST['dept'],
                        'batch_no'   => $_POST['batch_no'],
                        'phone'      => $_POST['phone'],
                        'photo'      => $photo,
                        'password'   => password_hash($_POST['password'], PASSWORD_DEFAULT),
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                    ]);
                    $success = "Registration successful! You can now login.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Mentor Sign Up</title>
    <link rel="stylesheet" href="/css/style.css?v=2">
</head>
<body class="auth-page signup">
<nav class="navbar" style="margin-bottom:20px;background:linear-gradient(135deg,#1a1a2e,#8e44ad);padding:12px 20px;border-radius:0 0 10px 10px;">
    <a href="index.php" style="display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;font-size:18px;font-weight:700;">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" style="height:35px;width:auto;">
        SGI <span style="font-size:13px;opacity:0.7;font-weight:400;">Mentor Portal</span>
    </a>
</nav>
<div class="auth-box signup-box">
    <h2>Create Mentor Account</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="text"   name="name"      placeholder="Full Name" required>
        <input type="text"   name="mentor_id" placeholder="Mentor ID (unique login ID)" required>
        <input type="email"  name="email"     placeholder="Email" required>
        <input type="text"   name="dept"      placeholder="Department" required>
        <input type="text"   name="batch_no"  placeholder="Batch No (e.g. 11TCS20)" required>
        <input type="tel"    name="phone"     placeholder="Phone Number" required>
        <label>Profile Photo (optional, = 200 KB)</label>
        <input type="file"   name="photo"     accept="image/*">
        <input type="password" name="password"         placeholder="Password (min 6 characters)" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <button type="submit" name="signup" class="btn-login">Create Account</button>
    </form>
    <p>Already have an account? <a href="mentor_login.php">Login</a></p>
    <div class="switch-role-container">
        <a href="index.php" class="switch-role-btn">
            <span class="switch-role-icon">🔄</span>
            <span>Switch to Student Portal</span>
        </a>
    </div>
</div>
<div id="popup" class="popup" style="display:none;">
    <div class="popup-box">
        <p id="popup-msg"></p>
        <button onclick="closePopup()">OK</button>
    </div>
</div>
<script>
function closePopup() { document.getElementById('popup').style.display = 'none'; }
function showPopup(msg, redirect) {
    document.getElementById('popup-msg').innerText = msg;
    document.getElementById('popup').style.display = 'flex';
    if (redirect) setTimeout(function() { window.location.href = 'mentor_login.php'; }, 2000);
}
document.querySelector('form').addEventListener('submit', function(e) {
    const pwd  = document.querySelector('[name=password]').value;
    const cpwd = document.querySelector('[name=confirm_password]').value;
    if (pwd !== cpwd) { e.preventDefault(); showPopup('Passwords do not match.'); }
});
<?php if ($error): ?>
window.onload = function() { showPopup('<?= addslashes($error) ?>'); };
<?php endif; ?>
<?php if (!empty($success)): ?>
window.onload = function() { showPopup('<?= addslashes($success) ?>', true); };
<?php endif; ?>
</script>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>
