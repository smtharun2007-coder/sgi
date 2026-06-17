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
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
<div class="auth-box">
    <h1>Student Growth Index</h1>
    <span class="role-badge mentor-badge">👨🏫 MENTOR PORTAL</span>
    <h2>Mentor Login</h2>
    <form method="POST">
        <input type="text"     name="mentor_id" placeholder="Mentor ID" required>
        <input type="password" name="password"  placeholder="Password"  required>
        <button type="submit" name="login" class="btn-login">Login</button>
    </form>
    <p>Don't have an account? <a href="mentor_signup.php">Sign Up</a></p>
    <p><a href="index.php">← Back to Home</a></p>
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
