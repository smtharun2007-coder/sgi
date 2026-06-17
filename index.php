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
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(12px);
            padding: 52px 44px 44px;
            border-radius: 28px;
            width: 90%;
            max-width: 460px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.45);
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }
        .welcome-box h1 {
            color: #1a1a2e;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: 1.5px;
            margin-bottom: 4px;
        }
        .welcome-box .tagline {
            color: #bbb;
            font-size: 13px;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }
        .welcome-divider {
            width: 48px;
            height: 4px;
            background: linear-gradient(90deg, #e94560, #8e44ad);
            border-radius: 4px;
            margin: 12px auto 32px;
        }
        .role-cards { display: flex; flex-direction: column; gap: 16px; }
        .role-card {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 22px 24px;
            border-radius: 18px;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            text-align: left;
            position: relative;
            overflow: hidden;
        }
        .role-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.08);
            opacity: 0;
            transition: opacity 0.2s;
        }
        .role-card:hover::before { opacity: 1; }
        .role-card::after {
            content: '→';
            position: absolute;
            right: 24px;
            font-size: 22px;
            color: rgba(255,255,255,0.5);
            transition: right 0.2s, color 0.2s;
        }
        .role-card:hover::after { right: 18px; color: rgba(255,255,255,0.9); }
        .role-card:hover { transform: translateY(-4px); box-shadow: 0 14px 36px rgba(0,0,0,0.25); }
        .role-card.student { background: linear-gradient(135deg, #1a1a2e 0%, #c0392b 60%, #e94560 100%); }
        .role-card.mentor  { background: linear-gradient(135deg, #1a1a2e 0%, #6c3483 60%, #8e44ad 100%); }
        .role-icon-wrap {
            width: 52px; height: 52px;
            background: rgba(255,255,255,0.15);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }
        .role-info .role-title { font-size: 18px; font-weight: 700; color: #fff; letter-spacing: 0.5px; }
        .role-info .role-desc  { font-size: 12px; color: rgba(255,255,255,0.65); margin-top: 4px; }
    </style>
</head>
<body class="auth-page">
<div class="welcome-box">
    <h1>SGI</h1>
    <p class="tagline">STUDENT GROWTH INDEX</p>
    <div class="welcome-divider"></div>
    <div class="role-cards">
        <a href="student_login.php" class="role-card student">
            <div class="role-icon-wrap">🎓</div>
            <div class="role-info">
                <div class="role-title">Student</div>
                <div class="role-desc">Track your academic growth & SGI score</div>
            </div>
        </a>
        <a href="mentor_login.php" class="role-card mentor">
            <div class="role-icon-wrap">👨🏫</div>
            <div class="role-info">
                <div class="role-title">Mentor</div>
                <div class="role-desc">Monitor & guide your students</div>
            </div>
        </a>
    </div>
</div>
</body>
</html>
