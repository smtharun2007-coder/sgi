<?php
include 'config.php';
if (isset($_SESSION['user'])) { header("Location: dashboard.php"); exit; }
$error = '';
if (isset($_GET['timeout'])) $error = "Session expired. Please login again.";

if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['lockout_time']))   $_SESSION['lockout_time']   = 0;

$locked = $_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['lockout_time']) < 300;

if (isset($_POST['login'])) {
    if ($locked) {
        $error = "Too many failed attempts. Try again in " . ceil((300 - (time() - $_SESSION['lockout_time'])) / 60) . " minute(s).";
    } else {
        $user = $users->findOne(['roll' => $_POST['roll']]);
        if ($user && password_verify($_POST['password'], $user['password'])) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['last_activity']  = time();
            $_SESSION['user'] = [
                'id'    => (string)$user['_id'],
                'name'  => $user['name'],
                'roll'  => $user['roll'],
                'email' => $user['email'] ?? '',
                'photo' => $user['photo'] ?? '',
            ];
            header("Location: dashboard.php");
            exit;
        }
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 5) $_SESSION['lockout_time'] = time();
        $remaining = 5 - $_SESSION['login_attempts'];
        $error = "Invalid Roll Number or Password." . ($remaining > 0 ? " ($remaining attempts left)" : " Account locked for 5 minutes.");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Student Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
<div class="auth-box">
    <div class="logo-container">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="logo-img">
    </div>
    <h1>Student Growth Index</h1>
    <div class="portal-badge student-portal">🎓 Student Portal</div>
    <h2>Login</h2>
    <form method="POST">
        <input type="text"     name="roll"     placeholder="Roll Number" required>
        <input type="password" name="password" placeholder="Password"    required>
        <button type="submit" name="login" class="btn-login">Login</button>
    </form>
    <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
    <p><a href="forgot_password.php">Forgot Password?</a></p>
    <div class="switch-role-container">
        <a href="index.php" class="switch-role-btn">
            <span class="switch-role-icon">🔄</span>
            <span>Switch to Mentor Portal</span>
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
</body>
</html>