<?php
include 'config.php';
include 'send_otp.php';

// Check if user is already logged in
if (isset($_SESSION['user'])) { header("Location: dashboard.php"); exit; }
if (isset($_SESSION['mentor'])) { header("Location: mentor_dashboard.php"); exit; }

$error = '';
$success = '';
$email_sent = false;
$step = isset($_POST['step']) ? (int)$_POST['step'] : (isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1);

// STEP 1: Enter ID (roll/mentor_id) and email to receive OTP
if (isset($_POST['send_otp'])) {
    $portal_type = $_POST['portal_type'] ?? 'student';
    
    if ($portal_type === 'mentor') {
        $mentor_id = trim($_POST['mentor_id']);
        $email = trim($_POST['email']);
        
        if (empty($mentor_id) || empty($email)) {
            $error = "Please enter both Mentor ID and Email.";
        } else {
            $mentor = $mentors->findOne(['mentor_id' => $mentor_id, 'email' => $email]);
            
            if ($mentor) {
                // Generate OTP
                $otp = generateOTP(6);
                $otp_expiry = time() + 600; // 10 minutes
                
                // Store OTP in session
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_otp'] = password_hash($otp, PASSWORD_DEFAULT);
                $_SESSION['reset_otp_time'] = $otp_expiry;
                $_SESSION['reset_portal_type'] = 'mentor';
                $_SESSION['reset_mentor_id'] = $mentor_id;
                $_SESSION['reset_user_id'] = (string)$mentor['_id'];
                $_SESSION['reset_step'] = 2;
                
                // Send OTP via email
                $name = $mentor['name'] ?? 'Mentor';
                $email_sent = sendOTPEmail($email, $name, $otp, 'mentor');
                
                if ($email_sent) {
                    $success = "OTP sent successfully to your email address!";
                    $step = 2;
                } else {
                    $error = "Failed to send OTP. Please try again or contact support.";
                }
            } else {
                $error = "Mentor ID and Email do not match our records.";
            }
        }
    } else {
        // Student portal
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
                
                if ($email_sent) {
                    $success = "OTP sent successfully to your email address!";
                    $step = 2;
                } else {
                    $error = "Failed to send OTP. Please try again or contact support.";
                }
            } else {
                $error = "Roll Number and Email do not match our records.";
            }
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
        header("Location: forgot_password.php");
        exit;
    }
    
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $portal_type = $_SESSION['reset_portal_type'];
    $email = $_SESSION['reset_email'];
    
    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
        $step = 3;
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
        $step = 3;
    } else {
        // Update password
        if ($portal_type === 'mentor') {
            $collection = $mentors;
            $identifier = ['mentor_id' => $_SESSION['reset_mentor_id']];
        } else {
            $collection = $users;
            $identifier = ['roll' => $_SESSION['reset_roll']];
        }
        
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
        $portal_type = $_SESSION['reset_portal_type'] ?? 'student';
        
        if ($portal_type === 'mentor') {
            $mentor = $mentors->findOne(['mentor_id' => $_SESSION['reset_mentor_id']]);
            if ($mentor) {
                $otp = generateOTP(6);
                $otp_expiry = time() + 600;
                
                $_SESSION['reset_otp'] = password_hash($otp, PASSWORD_DEFAULT);
                $_SESSION['reset_otp_time'] = $otp_expiry;
                
                $name = $mentor['name'] ?? 'Mentor';
                if (sendOTPEmail($_SESSION['reset_email'], $name, $otp, 'mentor')) {
                    $success = "New OTP sent to your email!";
                } else {
                    $error = "Failed to resend OTP.";
                }
            }
        } else {
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
                    $error = "Failed to resend OTP.";
                }
            }
        }
    }
    $step = 2;
}

// Determine portal type from query parameter
$portal_type = isset($_GET['portal']) ? $_GET['portal'] : 'student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Forgot Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
<div class="auth-box">
    <h1>Student Growth Index</h1>
    <?php if ($portal_type === 'mentor'): ?>
        <div class="portal-badge mentor-portal">👨 Mentor Portal</div>

    <?php endif; ?>
    <h2>Forgot Password</h2>

    <!-- PROGRESS STEPS -->
    <div style="display:flex;justify-content:center;align-items:center;gap:0;margin-bottom:24px;">
        <div style="width:30px;height:30px;border-radius:50%;background:<?= $step >= 1 ? '#e94560' : '#eee' ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;">1</div>
        <div style="width:40px;height:2px;background:<?= $step >= 2 ? '#e94560' : '#eee' ?>;"></div>
        <div style="width:30px;height:30px;border-radius:50%;background:<?= $step >= 2 ? '#e94560' : '#eee' ?>;color:<?= $step >= 2 ? '#fff' : '#aaa' ?>;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;">2</div>
        <div style="width:40px;height:2px;background:<?= $step >= 3 ? '#e94560' : '#eee' ?>;"></div>
        <div style="width:30px;height:30px;border-radius:50%;background:<?= $step >= 3 ? '#e94560' : '#eee' ?>;color:<?= $step >= 3 ? '#fff' : '#aaa' ?>;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;">3</div>
        <div style="width:40px;height:2px;background:<?= $step >= 4 ? '#27ae60' : '#eee' ?>;"></div>
        <div style="width:30px;height:30px;border-radius:50%;background:<?= $step >= 4 ? '#27ae60' : '#eee' ?>;color:<?= $step >= 4 ? '#fff' : '#aaa' ?>;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;">✓</div>
    </div>

    <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>

    <?php if ($step === 1): ?>
    <!-- STEP 1: Enter ID and email to receive OTP -->
    <form method="POST">
        <input type="hidden" name="step" value="1">
        <input type="hidden" name="portal_type" value="<?= htmlspecialchars($portal_type) ?>">
        
        <?php if ($portal_type === 'mentor'): ?>
            <input type="text" name="mentor_id" placeholder="Mentor ID" required>
        <?php else: ?>
            <input type="text" name="roll" placeholder="Roll Number" required>
        <?php endif; ?>
        
        <input type="email" name="email" placeholder="Registered Email Address" required>
        <button type="submit" name="send_otp" class="btn-login">Send OTP</button>
    </form>
    <p style="font-size: 13px; color: #888; margin-top: 16px;">
        We'll send a 6-digit OTP to your registered email address for verification.
    </p>

    <?php elseif ($step === 2): ?>
    <!-- STEP 2: Verify OTP -->
    <form method="POST">
        <input type="hidden" name="step" value="2">
        <p style="color: #555; margin-bottom: 20px;">
            Enter the 6-digit OTP sent to:<br>
            <strong><?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></strong>
        </p>
        <input type="text" name="otp" placeholder="Enter OTP (6 digits)" maxlength="6" required style="text-align: center; font-size: 24px; letter-spacing: 8px; font-weight: bold;">
        <button type="submit" name="verify_otp" class="btn-login">Verify OTP</button>
    </form>
    <div style="margin-top: 16px;">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="step" value="2">
            <button type="submit" name="resend_otp" style="background: none; border: none; color: #e94560; cursor: pointer; font-size: 14px; text-decoration: underline;">
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
        <button type="submit" name="reset_password" class="btn-login">Reset Password</button>
    </form>

    <?php elseif ($step === 4): ?>
    <!-- STEP 4: Success -->
    <div style="text-align: center; padding: 20px;">
        <div style="font-size: 64px; margin-bottom: 20px;">✅</div>
        <h3 style="color: #27ae60; margin-bottom: 10px;">Password Reset Successful!</h3>
        <p style="color: #555; margin-bottom: 24px;">Your password has been updated. You can now login with your new password.</p>
        <a href="<?= ($portal_type === 'mentor') ? 'mentor_login.php' : 'student_login.php' ?>" class="btn-login" style="display: inline-block; width: auto; padding: 12px 40px; text-decoration: none;">
            Go to Login
        </a>
    </div>
    <?php endif; ?>

    <p><a href="<?= ($portal_type === 'mentor') ? 'mentor_login.php' : 'student_login.php' ?>">← Back to Login</a></p>
    
    <?php if ($step < 4): ?>
    <div class="switch-role-container">
        <a href="forgot_password.php?portal=<?= ($portal_type === 'mentor') ? 'student' : 'mentor' ?>" class="switch-role-btn">
            <span class="switch-role-icon">🔄</span>
            <span>Switch to <?= ($portal_type === 'mentor') ? 'Student' : 'Mentor' ?> Portal</span>
        </a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>