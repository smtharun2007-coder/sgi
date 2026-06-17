<?php
include 'config.php';
if (isset($_SESSION['user'])) { header("Location: dashboard.php"); exit; }

$error = '';
$success = '';
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;

// STEP 1: Verify roll number and email
if (isset($_POST['verify'])) {
    $user = $users->findOne(['roll' => $_POST['roll'], 'email' => $_POST['email']]);
    if ($user) {
        $rollLast3 = substr($user['roll'], -3);
        $regLast3  = substr($user['reg'], -3);
        $combined  = $rollLast3 . $regLast3;
        if ($combined === $_POST['roll_reg_verify']) {
            $_SESSION['reset_roll'] = $_POST['roll'];
            $step = 2;
        } else {
            $error = "Verification code does not match.";
            $step = 1;
        }
    } else {
        $error = "Roll Number and Email do not match.";
        $step = 1;
    }
}

// STEP 2: Reset password
if (isset($_POST['reset'])) {
    if (empty($_SESSION['reset_roll'])) {
        header("Location: forgot_password.php"); exit;
    }
    if ($_POST['new_password'] !== $_POST['confirm_password']) {
        $error = "Passwords do not match.";
        $step = 2;
    } elseif (strlen($_POST['new_password']) < 6) {
        $error = "Password must be at least 6 characters.";
        $step = 2;
    } else {
        $users->updateOne(
            ['roll' => $_SESSION['reset_roll']],
            ['$set' => ['password' => password_hash($_POST['new_password'], PASSWORD_DEFAULT)]]
        );
        unset($_SESSION['reset_roll']);
        $success = "Password reset successfully! You can now login.";
        $step = 3;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Forgot Password</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body class="auth-page">
<div class="auth-box">
    <h1>Student Growth Index</h1>
    <span class="role-badge student-badge">🎓 STUDENT PORTAL</span>
    <h2>Forgot Password</h2>

    <!-- PROGRESS STEPS -->
    <div style="display:flex;justify-content:center;align-items:center;gap:0;margin-bottom:24px;">
        <div style="width:30px;height:30px;border-radius:50%;background:<?= $step >= 1 ? '#e94560' : '#eee' ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;">1</div>
        <div style="width:40px;height:2px;background:<?= $step >= 2 ? '#e94560' : '#eee' ?>;"></div>
        <div style="width:30px;height:30px;border-radius:50%;background:<?= $step >= 2 ? '#e94560' : '#eee' ?>;color:<?= $step >= 2 ? '#fff' : '#aaa' ?>;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;">2</div>
        <div style="width:40px;height:2px;background:<?= $step >= 3 ? '#e94560' : '#eee' ?>;"></div>
        <div style="width:30px;height:30px;border-radius:50%;background:<?= $step >= 3 ? '#27ae60' : '#eee' ?>;color:<?= $step >= 3 ? '#fff' : '#aaa' ?>;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;">✓</div>
    </div>

    <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>

    <?php if ($step === 1): ?>
    <!-- STEP 1: Verify Identity -->
    <form method="POST">
        <input type="hidden" name="step" value="1">
        <input type="text"  name="roll"             placeholder="Roll Number" required>
        <input type="email" name="email"            placeholder="Registered Email" required>
        <input type="text"  name="roll_reg_verify"  placeholder="Last 3 of Roll + Last 3 of Integrated (e.g. 123456)" maxlength="6" required>
        <button type="submit" name="verify" class="btn-login">Verify Identity</button>
    </form>

    <?php elseif ($step === 2): ?>
    <!-- STEP 2: Reset Password -->
    <form method="POST">
        <input type="hidden" name="step" value="2">
        <input type="password" name="new_password"     placeholder="New Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
        <button type="submit" name="reset" class="btn-login">Reset Password</button>
    </form>

    <?php elseif ($step === 3): ?>
    <!-- STEP 3: Done -->
    <a href="student_login.php" class="btn-login" style="display:block;text-align:center;text-decoration:none;margin-top:10px;">Go to Login</a>
    <?php endif; ?>

    <p><a href="student_login.php">← Back to Login</a></p>
</div>
</body>
</html>

