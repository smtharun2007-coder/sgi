<?php
include 'config.php';
if (isset($_SESSION['user']))   { header("Location: dashboard.php"); exit; }
if (isset($_SESSION['mentor'])) { header("Location: mentor_dashboard.php"); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Welcome</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="logo1.jpeg">
    <style>
        .welcome-box {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 50px 40px 40px;
            border-radius: 24px;
            width: 90%;
            max-width: 460px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.4);
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }
        .welcome-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 16px;
            margin-bottom: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        .welcome-box h1 {
            color: #1a1a2e;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .welcome-box .tagline {
            color: #aaa;
            font-size: 13px;
            margin-bottom: 36px;
        }
        .role-cards { display: flex; flex-direction: column; gap: 14px; }
        .role-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px 22px;
            border-radius: 16px;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            text-align: left;
            position: relative;
            overflow: hidden;
        }
        .role-card::after {
            content: '→';
            position: absolute;
            right: 22px;
            font-size: 20px;
            color: rgba(255,255,255,0.6);
            transition: right 0.2s;
        }
        .role-card:hover::after { right: 16px; }
        .role-card:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,0.2); }
        .role-card.student { background: linear-gradient(135deg, #1a1a2e 0%, #e94560 100%); }
        .role-card.mentor  { background: linear-gradient(135deg, #1a1a2e 0%, #8e44ad 100%); }
        .role-icon { font-size: 38px; line-height: 1; }
        .role-info .role-title { font-size: 17px; font-weight: 700; color: #fff; }
        .role-info .role-desc  { font-size: 12px; color: rgba(255,255,255,0.7); margin-top: 3px; }
    </style>
</head>
<body class="auth-page">
<div class="welcome-box">
    <img src="logo1.jpeg" class="welcome-logo" alt="SGI Logo">
    <h1>Student Growth Index</h1>
    <p class="tagline">Select your role to continue</p>
    <div class="role-cards">
        <a href="student_login.php" class="role-card student">
            <div class="role-icon">🎓</div>
            <div class="role-info">
                <div class="role-title">Student</div>
                <div class="role-desc">Track your academic growth & SGI</div>
            </div>
        </a>
        <a href="mentor_login.php" class="role-card mentor">
            <div class="role-icon">👨‍🏫</div>
            <div class="role-info">
                <div class="role-title">Mentor</div>
                <div class="role-desc">Monitor & guide your students</div>
            </div>
        </a>
    </div>
</div>
</body>
</html>
