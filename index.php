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
    <link rel="stylesheet" href="/css/style.css?v=2">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .welcome-box {
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(12px);
            padding: 48px 40px 52px;
            border-radius: 28px;
            width: 92%;
            max-width: 520px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.45);
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }
        .logo-container {
            margin-bottom: 20px;
        }
        .logo-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        .sgi-title {
            font-size: 64px;
            font-weight: 900;
            letter-spacing: 6px;
            background: linear-gradient(135deg, #e94560, #8e44ad);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 6px;
        }
        .sgi-full {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 32px;
            opacity: 0.7;
        }
        .role-cards { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 16px; 
        }
        .role-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 28px 20px;
            border-radius: 18px;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            text-align: center;
            position: relative;
            aspect-ratio: 1;
        }
        .role-card:hover { 
            transform: translateY(-6px); 
            box-shadow: 0 16px 40px rgba(0,0,0,0.3); 
        }
        .role-card.student { 
            background: linear-gradient(135deg, #1a1a2e, #e94560); 
        }
        .role-card.mentor { 
            background: linear-gradient(135deg, #1a1a2e, #8e44ad); 
        }
        .role-icon-wrap {
            width: 56px; 
            height: 56px;
            background: rgba(255,255,255,0.15);
            border-radius: 16px;
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }
        .role-title { 
            font-size: 18px; 
            font-weight: 700; 
            color: #fff; 
            letter-spacing: 1px;
        }
        @media (max-width: 480px) {
            .role-cards { grid-template-columns: 1fr; }
            .role-card { aspect-ratio: auto; padding: 24px 20px; }
        }
    </style>
</head>
<body class="auth-page">
<div class="welcome-box">
    <div class="logo-container">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="logo-img">
    </div>
    <div class="sgi-title">SGI</div>
    <div class="sgi-full">Student Growth Index</div>
    <div class="role-cards">
        <a href="student_login.php" class="role-card student">
            <div class="role-icon-wrap">👨‍🎓</div>
            <div class="role-title">Student</div>
        </a>
        <a href="mentor_login.php" class="role-card mentor">
            <div class="role-icon-wrap">👨‍🏫</div>
            <div class="role-title">Mentor</div>
        </a>
    </div>
    <div class="copyright-footer" style="margin-top:24px;padding-top:20px;border-top:1px solid rgba(0,0,0,0.1);color:rgba(0,0,0,0.5);font-size:11px;">
        &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
    </div>
</div>
</body>
</html>
