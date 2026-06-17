<?php
include 'config.php';
if (isset($_SESSION['user'])) { header("Location: dashboard.php"); exit; }
$error = '';
if (isset($_POST['signup'])) {
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error = "Passwords do not match.";
    } else {
        $existing = $users->findOne(['roll' => $_POST['roll']]);
        if ($existing) {
            $error = "Roll Number already registered.";
        } else {
            $photo = '';
            if (!empty($_FILES['photo']['name'])) {
                if ($_FILES['photo']['size'] > 200 * 1024) { $error = "Profile photo must be ≤ 200 KB."; }
                else {
                    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                    $photo = uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['photo']['tmp_name'], "uploads/" . $photo);
                }
            }
            $signature = '';
            if (!$error && !empty($_FILES['signature']['name'])) {
                if ($_FILES['signature']['size'] > 100 * 1024) { $error = "Signature must be ≤ 100 KB."; }
                else {
                    $ext = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
                    $signature = 'sig_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['signature']['tmp_name'], "uploads/" . $signature);
                }
            }
            if (!$error) {
            $users->insertOne([
                'name'      => $_POST['name'],
                'roll'      => $_POST['roll'],
                'reg'       => $_POST['reg'],
                'dob'       => $_POST['dob'],
                'father'    => $_POST['father'],
                'mother'    => $_POST['mother'],
                'email'     => $_POST['email'],
                'dept'      => $_POST['dept'],
                'year_from' => (int)$_POST['year_from'],
                'year_to'   => (int)$_POST['year_to'],
                'class'     => $_POST['class'],
                'phone'     => $_POST['phone'],
                'mentor_id' => $_POST['mentor_id'],
                'photo'     => $photo,
                'signature' => $signature,
                'password'  => password_hash($_POST['password'], PASSWORD_DEFAULT),
            ]);
            $success = "Registration successful! You can now login.";
            } // end !$error
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Sign Up</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="logo1.jpeg">
</head>
<body class="auth-page">
<div class="auth-box signup-box">
    <h1>Student Growth Index</h1>
    <div class="portal-badge student-portal">🎓 Student Portal</div>
    <h2>Create Student Account</h2>
    <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <input type="text"   name="name"      placeholder="Full Name" required>
        <input type="text"   name="roll"      placeholder="Roll Number" required>
        <input type="text"   name="reg"       placeholder="Integrated Number" required>
        <label>Date of Birth</label>
        <input type="date"   name="dob"       required>
        <input type="text"   name="father"    placeholder="Father's Name" required>
        <input type="text"   name="mother"    placeholder="Mother's Name" required>
        <input type="email"  name="email"     placeholder="Email" required>
        <input type="text"   name="dept"      placeholder="Department" required>
        <input type="number" name="year_from" placeholder="Academic Year From (e.g. 2022)" required>
        <input type="number" name="year_to"   placeholder="Academic Year To (e.g. 2026)" required>
        <input type="text"   name="class"     placeholder="Class (e.g. CSE-A)" required>
        <input type="tel"    name="phone"     placeholder="Phone Number" required>
        <input type="text"   name="mentor_id"  placeholder="Mentor ID" required>
        <label>Student Photo</label>
        <input type="file"   name="photo"     accept="image/*" required>
        <label>Student Signature</label>
        <input type="file"   name="signature" accept="image/*" required>
        <input type="password" name="password"         placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <button type="submit" name="signup" class="btn-login">Sign Up</button>
    </form>
    <p>Already have an account? <a href="student_login.php">Login</a></p>
    <p style="margin-top:20px;padding-top:16px;border-top:1px solid #eee;"><a href="index.php" style="color:#aaa;font-size:12px;">← Switch Role</a></p>
</div>
<div id="popup" class="popup" style="display:none;">
    <div class="popup-box">
        <p id="popup-msg"></p>
        <button onclick="closePopup()">OK</button>
    </div>
</div>
<script>
function showPopup(msg, redirect) {
    document.getElementById('popup-msg').innerText = msg;
    document.getElementById('popup').style.display = 'flex';
    if (redirect) setTimeout(function() { window.location.href = 'student_login.php'; }, 2000);
}
function closePopup() { document.getElementById('popup').style.display = 'none'; }
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

