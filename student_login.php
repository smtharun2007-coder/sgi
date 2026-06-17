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
                'id'        => (string)$user['_id'],
                'name'      => $user['name'],
                'roll'      => $user['roll'],
                'reg'       => $user['reg'],
                'email'     => $user['email'],
                'dob'       => $user['dob'],
                'dept'      => $user['dept'],
                'class'     => $user['class'],
                'year_from' => $user['year_from'],
                'year_to'   => $user['year_to'],
                'phone'     => $user['phone'],
                'mentor_id' => $user['mentor_id'] ?? '',
                'photo'     => $user['photo'],
                'signature' => $user['signature'],
                'father'    => $user['father'],
                'mother'    => $user['mother'],
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
    <link rel="icon" type="image/jpeg" href="logo1.jpeg">
</head>
<body class="auth-page">
<div class="auth-box">
    <img src="logo1.jpeg" class="auth-logo" alt="SGI">
    <h1>Student Growth Index</h1>
    <div class="portal-badge student-portal">🎓 Student Portal</div>
    <h2>Login to your account</h2>
    <form method="POST">
        <input type="text"     name="roll"     placeholder="Roll Number" required>
        <input type="password" name="password" placeholder="Password"    required>
        <button type="submit" name="login" class="btn-login">Login</button>
    </form>
    <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
    <p><a href="forgot_password.php">Forgot Password?</a></p>
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
<?php if ($error): ?>
window.onload = function() {
    document.getElementById('popup-msg').innerText = '<?= addslashes($error) ?>';
    document.getElementById('popup').style.display = 'flex';
};
<?php endif; ?>
</script>
</body>
</html>
