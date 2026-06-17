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
                    if ($_FILES['photo']['size'] > 200 * 1024) {
                        $error = "Profile photo must be ≤ 200 KB.";
                    } else {
                        $ext   = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                        $photo = 'mentor_' . uniqid() . '.' . $ext;
                        move_uploaded_file($_FILES['photo']['tmp_name'], "uploads/" . $photo);
                    }
                }
                if (!$error) {
                    $mentors->insertOne([
                        'name'       => $_POST['name'],
                        'mentor_id'  => $_POST['mentor_id'],
                        'email'      => $_POST['email'],
                        'dept'       => $_POST['dept'],
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
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="logo1.jpeg">
</head>
<body class="auth-page">
<div class="auth-box signup-box">
    <h1>Student Growth Index</h1>
    <div class="portal-badge mentor-portal">👨🏫 Mentor Portal</div>
    <h2>Create Mentor Account</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="text"   name="name"      placeholder="Full Name" required>
        <input type="text"   name="mentor_id" placeholder="Mentor ID (unique login ID)" required>
        <input type="email"  name="email"     placeholder="Email" required>
        <input type="text"   name="dept"      placeholder="Department" required>
        <input type="tel"    name="phone"     placeholder="Phone Number" required>
        <label>Profile Photo (optional, ≤ 200 KB)</label>
        <input type="file"   name="photo"     accept="image/*">
        <input type="password" name="password"         placeholder="Password (min 6 characters)" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <button type="submit" name="signup" class="btn-login">Create Account</button>
    </form>
    <p>Already have an account? <a href="mentor_login.php">Login</a></p>
    <p style="margin-top:20px;padding-top:16px;border-top:1px solid #eee;"><a href="index.php" style="color:#aaa;font-size:12px;">← Switch Role</a></p>
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
</body>
</html>
