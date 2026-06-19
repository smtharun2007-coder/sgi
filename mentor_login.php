<?php
include 'config.php';
if (isset($_SESSION['mentor'])) { header("Location: mentor_dashboard.php"); exit; }
$error = '';
if (isset($_GET['timeout'])) $error = "Session expired. Please login again.";

if (!isset($_SESSION['mentor_login_attempts'])) $_SESSION['mentor_login_attempts'] = 0;
if (!isset($_SESSION['mentor_lockout_time']))   $_SESSION['mentor_lockout_time']   = 0;

$locked = $_SESSION['mentor_login_attempts'] >= 5 && (time() - $_SESSION['mentor_lockout_time']) < 300;

if (isset($_POST['login'])) {
    if ($locked) {
        $error = "Too many failed attempts. Try again in " . ceil((300 - (time() - $_SESSION['mentor_lockout_time'])) / 60) . " minute(s).";
    } else {
        $mentor = $mentors->findOne(['mentor_id' => $_POST['mentor_id']]);
        if ($mentor && password_verify($_POST['password'], $mentor['password'])) {
            $_SESSION['mentor_login_attempts'] = 0;
            $_SESSION['last_mentor_activity']  = time();
            $_SESSION['mentor'] = [
                'id'        => (string)$mentor['_id'],
                'name'      => $mentor['name'],
                'mentor_id' => $mentor['mentor_id'],
                'email'     => $mentor['email'],
                'dept'      => $mentor['dept'],
                'phone'     => $mentor['phone'],
                'photo'     => $mentor['photo'] ?? '',
            ];
            header("Location: mentor_dashboard.php");
            exit;
        }
        $_SESSION['mentor_login_attempts']++;
        if ($_SESSION['mentor_login_attempts'] >= 5) $_SESSION['mentor_lockout_time'] = time();
        $remaining = 5 - $_SESSION['mentor_login_attempts'];
        $error = "Invalid Mentor ID or Password." . ($remaining > 0 ? " ($remaining attempts left)" : " Account locked for 5 minutes.");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Mentor Login</title>
    <link rel="stylesheet" href="/css/style.css?v=2">
</head>
<body class="auth-page">
<div class="auth-box">
    <div class="logo-container">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="logo-img">
    </div>
    <h1>Student Growth Index</h1>
    <div class="portal-badge mentor-portal">🧑‍🏫 Mentor Portal</div>
    <h2>Login</h2>
    <form method="POST">
        <input type="text"     name="mentor_id" placeholder="Mentor ID" required>
        <input type="password" name="password"  placeholder="Password"  required>
        <button type="submit" name="login" class="btn-login">Login</button>
    </form>
    <p>Don't have an account? <a href="mentor_signup.php">Sign Up</a></p>
    <p><a href="forgot_password_mentor.php">Forgot Password?</a></p>
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
<?php if ($error): ?>
window.onload = function() {
    document.getElementById('popup-msg').innerText = '<?= addslashes($error) ?>';
    document.getElementById('popup').style.display = 'flex';
};
<?php endif; ?>
</script>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>
