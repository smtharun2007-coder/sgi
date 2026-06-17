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
    <link rel="icon" type="image/jpeg" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .welcome-box {
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(12px);
            padding: 56px 44px 48px;
            border-radius: 28px;
            width: 90%;
            max-width: 460px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.45);
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }
        .sgi-title {
            font-size: 72px;
            font-weight: 900;
            letter-spacing: 6px;
            background: linear-gradient(135deg, #e94560, #8e44ad);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 8px;
        }
        .sgi-full {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 36px;
            opacity: 0.7;
        }
        .role-cards { display: flex; flex-direction: column; gap: 14px; }
        .role-card {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 22px 26px;
            border-radius: 18px;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            text-align: left;
            position: relative;
        }
        .role-card::after {
            content: '→';
            position: absolute;
            right: 24px;
            font-size: 20px;
            color: rgba(255,255,255,0.5);
            transition: right 0.2s, color 0.2s;
        }
        .role-card:hover::after { right: 18px; color: #fff; }
        .role-card:hover { transform: translateY(-4px); box-shadow: 0 14px 36px rgba(0,0,0,0.25); }
        .role-card.student { background: linear-gradient(135deg, #1a1a2e, #e94560); }
        .role-card.mentor  { background: linear-gradient(135deg, #1a1a2e, #8e44ad); }
        .role-icon-wrap {
            width: 50px; height: 50px;
            background: rgba(255,255,255,0.15);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .role-title { font-size: 18px; font-weight: 700; color: #fff; }
    </style>
</head>
<body class="auth-page">
<div class="welcome-box">
    <div class="sgi-title">SGI</div>
    <div class="sgi-full">Student Growth Index</div>
    <div class="role-cards">
        <a href="student_login.php" class="role-card student">
            <div class="role-icon-wrap">📚</div>
            <div class="role-title">Student</div>
        </a>
        <a href="mentor_login.php" class="role-card mentor">
            <div class="role-icon-wrap">🧑‍🏫</div>
            <div class="role-title">Mentor</div>
        </a>
    </div>
</div>
</body>
</html>
