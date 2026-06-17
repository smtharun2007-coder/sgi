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
    <style>
        .welcome-box {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 50px 40px;
            border-radius: 20px;
            width: 90%;
            max-width: 480px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.1);
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }
        .welcome-box h1 { color: #e94560; font-size: 28px; font-weight: 700; letter-spacing: 1px; margin-bottom: 6px; }
        .welcome-box p  { color: #888; font-size: 14px; margin-bottom: 36px; }
        .role-cards     { display: flex; flex-direction: column; gap: 16px; }
        .role-card {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 20px 24px;
            border-radius: 14px;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            text-align: left;
        }
        .role-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
        .role-card.student  { background: linear-gradient(135deg, #1a1a2e, #e94560); }
        .role-card.mentor   { background: linear-gradient(135deg, #1a1a2e, #8e44ad); }
        .role-card-icon { font-size: 36px; }
        .role-card-info .role-title { font-size: 18px; font-weight: 700; color: #fff; }
        .role-card-info .role-desc  { font-size: 13px; color: rgba(255,255,255,0.75); margin-top: 3px; }
        .role-arrow { margin-left: auto; font-size: 20px; color: rgba(255,255,255,0.7); }
    </style>
</head>
<body class="auth-page">
<div class="welcome-box">
    <h1>Student Growth Index</h1>
    <p>Select your role to continue</p>
    <div class="role-cards">
        <a href="student_login.php" class="role-card student">
            <div class="role-card-icon">🎓</div>
            <div class="role-card-info">
                <div class="role-title">Student</div>
                <div class="role-desc">Track your academic growth & SGI</div>
            </div>
            <div class="role-arrow">→</div>
        </a>
        <a href="mentor_login.php" class="role-card mentor">
            <div class="role-card-icon">👨‍🏫</div>
            <div class="role-card-info">
                <div class="role-title">Mentor</div>
                <div class="role-desc">Monitor & guide your students</div>
            </div>
            <div class="role-arrow">→</div>
        </a>
    </div>
</div>
</body>
</html>
