<?php
include 'config.php';
include 'send_otp.php';

// Check if user is already logged in
if (isset($_SESSION['user'])) { header("Location: dashboard.php"); exit; }
if (isset($_SESSION['mentor'])) { header("Location: mentor_dashboard.php"); exit; }

$error = '';
$success = '';
$email_sent = false;

// Force portal type to student
$portal_type = 'student';

// Clear any existing password reset session data when starting fresh (not during resend)
if (!isset($_POST['resend_otp']) && !isset($_POST['verify_otp']) && !isset($_POST['reset_password'])) {
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_otp']);
    unset($_SESSION['reset_otp_time']);
    unset($_SESSION['reset_portal_type']);
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_roll']);
    unset($_SESSION['reset_mentor_id']);
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_verified']);
}

// Determine step - check session first, then POST
$step = 1;
if (isset($_SESSION['reset_step'])) {
    $step = (int)$_SESSION['reset_step'];
} elseif (isset($_POST['step'])) {
    $step = (int)$_POST['step'];
}

// STEP 1: Enter roll number and email to receive OTP
if (isset($_POST['send_otp'])) {
    $roll = trim($_POST['roll']);
    $email = trim($_POST['email']);
    
    if (empty($roll) || empty($email)) {
        $error = "Please enter both Roll Number and Email.";
    } else {
        $user = $users->findOne(['roll' => $roll, 'email' => $email]);
        
        if ($user) {
            // Generate OTP
            $otp = generateOTP(6);
            $otp_expiry = time() + 600; // 10 minutes
            
            // Store OTP in session
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_otp'] = password_hash($otp, PASSWORD_DEFAULT);
            $_SESSION['reset_otp_time'] = $otp_expiry;
            $_SESSION['reset_portal_type'] = 'student';
            $_SESSION['reset_roll'] = $roll;
            $_SESSION['reset_user_id'] = (string)$user['_id'];
            $_SESSION['reset_step'] = 2;
            
            // Send OTP via email
            $name = $user['name'] ?? 'Student';
            $email_sent = sendOTPEmail($email, $name, $otp, 'student');
            
            // Always move to step 2 - user can verify OTP even if email fails
            $step = 2;
            
            if ($email_sent) {
                $success = "OTP sent successfully to your email address! Please check your inbox and spam folder.";
            } else {
                // Show warning but still allow user to proceed to step 2
                // For debugging, show more details in development mode
                if (getenv('APP_DEBUG') === 'true') {
                    $error = "Warning: Could not send OTP via email. Check server logs for details. You may still enter the OTP if you received it through other means.";
                } else {
                    $error = "Warning: Could not send OTP via email. Please contact support or try again later. You may still enter the OTP if you received it through other means.";
                }
            }
        } else {
            $error = "Roll Number and Email do not match our records.";
        }
    }
}

// STEP 2: Verify OTP
if (isset($_POST['verify_otp'])) {
    if (empty($_SESSION['reset_otp']) || empty($_SESSION['reset_otp_time'])) {
        $error = "Session expired. Please request a new OTP.";
        $step = 1;
    } else {
        $entered_otp = $_POST['otp'];
        $stored_otp_hash = $_SESSION['reset_otp'];
        $otp_time = $_SESSION['reset_otp_time'];
        
        // Check if OTP has expired
        if (time() > $otp_time) {
            $error = "OTP has expired. Please request a new one.";
            unset($_SESSION['reset_otp']);
            unset($_SESSION['reset_otp_time']);
            $step = 1;
        } elseif (password_verify($entered_otp, $stored_otp_hash)) {
            // OTP is correct
            $_SESSION['reset_verified'] = true;
            $_SESSION['reset_step'] = 3;
            $step = 3;
            $success = "OTP verified successfully! You can now reset your password.";
            
            unset($_SESSION['reset_otp']);
            unset($_SESSION['reset_otp_time']);
        } else {
            $error = "Invalid OTP. Please try again.";
            $step = 2;
        }
    }
}

// STEP 3: Reset password
if (isset($_POST['reset_password'])) {
    if (empty($_SESSION['reset_verified']) || empty($_SESSION['reset_email'])) {
        $error = "Please verify OTP first.";
        header("Location: forgot_password_student.php");
        exit;
    }
    
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_SESSION['reset_email'];
    
    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
        $step = 3;
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
        $step = 3;
    } else {
        // Update password for student
        $collection = $users;
        $identifier = ['roll' => $_SESSION['reset_roll']];
        
        $result = $collection->updateOne(
            $identifier,
            ['$set' => ['password' => password_hash($new_password, PASSWORD_DEFAULT)]]
        );
        
        if ($result->getModifiedCount() > 0) {
            // Clear all reset session variables
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_verified']);
            unset($_SESSION['reset_portal_type']);
            unset($_SESSION['reset_step']);
            unset($_SESSION['reset_roll']);
            unset($_SESSION['reset_mentor_id']);
            unset($_SESSION['reset_user_id']);
            
            $success = "Password reset successfully! You can now login with your new password.";
            $step = 4;
        } else {
            $error = "Failed to reset password. Please try again.";
            $step = 3;
        }
    }
}

// Resend OTP
if (isset($_POST['resend_otp'])) {
    if (!empty($_SESSION['reset_email'])) {
        $user = $users->findOne(['roll' => $_SESSION['reset_roll']]);
        if ($user) {
            $otp = generateOTP(6);
            $otp_expiry = time() + 600;
            
            $_SESSION['reset_otp'] = password_hash($otp, PASSWORD_DEFAULT);
            $_SESSION['reset_otp_time'] = $otp_expiry;
            
            $name = $user['name'] ?? 'Student';
            if (sendOTPEmail($_SESSION['reset_email'], $name, $otp, 'student')) {
                $success = "New OTP sent to your email!";
            } else {
                $error = "Failed to resend OTP. Please check email configuration.";
            }
        }
    }
    $step = 2;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Student Forgot Password</title>
    <link rel="stylesheet" href="/css/style.css?v=2">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        body.auth-page { background: #f4f6fb; min-height: 100vh; margin: 0; }
        .reset-nav { background: linear-gradient(135deg, #1a1a2e, #e94560); color: #fff; padding: 18px 6%; }
        .reset-nav a { color: #fff; text-decoration: none; font-weight: 700; }
        .reset-shell { max-width: 720px; margin: 0 auto; padding: 28px 20px 48px; }
        .reset-card { background: #fff; border-radius: 16px; padding: 34px; box-shadow: 0 4px 16px rgba(0,0,0,.08); }
        .reset-card h1, .reset-card h2 { color: #1a1a2e; }
        .reset-card h1 { margin: 0 0 8px; font-size: 26px; }
        .reset-card h2 { margin: 0 0 20px; }
        .reset-intro { color: #666; line-height: 1.7; }
        .reset-status { display: flex; gap: 8px; margin: 24px 0; }
        .reset-status span { flex: 1; padding: 10px 6px; border-radius: 8px; text-align: center; font-size: 12px; font-weight: 700; background: #eef0f5; color: #777; }
        .reset-status span.active { background: #e94560; color: #fff; }
        .reset-status span.complete { background: #eaffea; color: #218838; }
        .otp-hint { color: #666; font-size: 13px; line-height: 1.6; }
        .otp-input { text-align: center; font-size: 24px; letter-spacing: 8px; font-weight: 700; }
        .reset-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .reset-actions button { flex: 1; min-width: 160px; }
        .resend-form { margin-top: 14px; text-align: center; }
        .resend-form button { background: none; border: 0; color: #e94560; cursor: pointer; text-decoration: underline; }
        @media (max-width: 520px) { .reset-card { padding: 24px 18px; } .reset-status span { font-size: 10px; } }
    </style>
</head>
<body class="auth-page">
<nav class="reset-nav"><a href="index.php">SGI <span style="font-size:13px;opacity:.75;font-weight:400;">Student Portal</span></a></nav>
<main class="reset-shell">
<div class="reset-card">
    <h1>Student Growth Index</h1>
    <p class="reset-intro">Reset your student portal password securely. We will verify your registered details before allowing a password change.</p>
    <h2>Forgot Password</h2>

    <div class="reset-status">
        <span class="<?= $step >= 1 ? 'active' : '' ?>">1. Send OTP</span>
        <span class="<?= $step >= 2 ? 'active' : '' ?>">2. Verify</span>
        <span class="<?= $step >= 3 ? 'active' : '' ?>">3. Reset</span>
        <span class="<?= $step >= 4 ? 'complete' : '' ?>">4. Done</span>
    </div>

    <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>

    <?php if ($step === 1): ?>
    <!-- STEP 1: Enter roll number and email to receive OTP -->
    <form method="POST">
        <input type="hidden" name="step" value="1">
        <input type="text" name="roll" placeholder="Roll Number" required>
        <input type="email" name="email" placeholder="Registered Email Address" required>
        <button type="submit" name="send_otp" class="btn-primary">Send OTP</button>
    </form>
    <p class="otp-hint">
        We'll send a 6-digit OTP to your registered email address for verification.
    </p>

    <?php elseif ($step === 2): ?>
    <!-- STEP 2: Verify OTP -->
    <form method="POST">
        <input type="hidden" name="step" value="2">
        <p class="otp-hint">
            Enter the 6-digit OTP sent to:<br>
            <strong><?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></strong>
        </p>
        <input class="otp-input" type="text" name="otp" placeholder="Enter 6-digit OTP" maxlength="6" required>
        <button type="submit" name="verify_otp" class="btn-primary">Verify OTP</button>
    </form>
    <div class="resend-form">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="step" value="2">
            <button type="submit" name="resend_otp">
                Resend OTP
            </button>
        </form>
    </div>

    <?php elseif ($step === 3): ?>
    <!-- STEP 3: Reset password -->
    <form method="POST">
        <input type="hidden" name="step" value="3">
        <input type="password" name="new_password" placeholder="New Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
        <button type="submit" name="reset_password" class="btn-primary">Reset Password</button>
    </form>

    <?php elseif ($step === 4): ?>
    <!-- STEP 4: Success -->
    <div style="text-align: center; padding: 20px;">
        <div style="font-size: 64px; margin-bottom: 20px;">✅</div>
        <h3 style="color: #27ae60; margin-bottom: 10px;">Password Reset Successful!</h3>
        <p style="color: #555; margin-bottom: 24px;">Your password has been updated. You can now login with your new password.</p>
        <a href="student_login.php" class="btn-primary" style="display: inline-block; width: auto; padding: 12px 40px; text-decoration: none;">
            Go to Login
        </a>
    </div>
    <?php endif; ?>

    <p style="margin-top:24px;"><a href="student_login.php">← Back to Login</a></p>
    
    <?php if ($step < 4): ?>
    <div class="switch-role-container">
        <a href="index.php" class="switch-role-btn">
            <span class="switch-role-icon">🔄</span>
            <span>Switch to Mentor Portal</span>
        </a>
 </div>
    <?php endif; ?>
    <div class="copyright-footer" style="margin-top:24px;padding-top:20px;border-top:1px solid rgba(0,0,0,0.1);color:rgba(0,0,0,0.5);font-size:11px;">
        &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
    </div>
</div>
</main>
</body>
</html>
