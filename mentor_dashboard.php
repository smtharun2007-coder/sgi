<?php
include 'config.php';
if (!isset($_SESSION['mentor'])) { header("Location: mentor_login.php"); exit; }

// Mentor session timeout - 30 minutes
if (isset($_SESSION['last_mentor_activity']) && (time() - $_SESSION['last_mentor_activity'] > 1800)) {
    unset($_SESSION['mentor']);
    header("Location: mentor_login.php?timeout=1");
    exit;
}
$_SESSION['last_mentor_activity'] = time();

$m = $_SESSION['mentor'];

// Fetch all students linked to this mentor
$studentCursor = $users->find(['mentor_id' => $m['mentor_id']]);
$studentList   = iterator_to_array($studentCursor);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Mentor Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .mentor-badge {
            display: inline-block;
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            color: #fff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .mentor-navbar { background: linear-gradient(135deg, #1a1a2e, #8e44ad); }
        .student-list-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            margin-top: 24px;
        }
        .student-list-header {
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            padding: 16px 24px;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
        }
        .student-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            border-bottom: 1px solid #f0f2f5;
        }
        .student-row:last-child { border-bottom: none; }
        .student-row:hover { background: #fafafa; }
        .student-avatar {
            width: 44px; height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #8e44ad;
            margin-right: 14px;
        }
        .student-avatar-placeholder {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 16px;
            margin-right: 14px; flex-shrink: 0;
        }
        .student-info { flex: 1; }
        .student-info .s-name { font-size: 15px; font-weight: 700; color: #1a1a2e; }
        .student-info .s-roll { font-size: 13px; color: #888; margin-top: 2px; }
        .student-info .s-dept { font-size: 12px; color: #aaa; }
    </style>
</head>
<body>
<nav class="navbar mentor-navbar">
    <a href="mentor_dashboard.php" class="nav-brand">SGI <span style="font-size:13px;opacity:0.7;font-weight:400;">Mentor</span></a>
    <div class="nav-links">
        <a href="mentor_dashboard.php">Home</a>
        <a href="mentor_update_profile.php">Update Profile</a>
        <a href="mentor_logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">

    <!-- MENTOR PROFILE CARD -->
    <div class="profile-card">
        <div class="profile-info">
            <h2><?= htmlspecialchars($m['name']) ?></h2>
            <p class="profile-roll"><?= htmlspecialchars($m['mentor_id']) ?></p>
            <p><strong>Department:</strong> <?= htmlspecialchars($m['dept']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($m['email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($m['phone']) ?></p>
            <div style="margin-top:14px;"><span class="mentor-badge">MENTOR</span></div>
        </div>
        <div class="profile-photo">
            <?php if (!empty($m['photo'])): ?>
                <img src="<?= htmlspecialchars(imgUrl($m['photo'])) ?>" alt="Photo">
            <?php else: ?>
                <div class="no-photo">No Photo</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SUMMARY -->
    <div class="summary-row">
        <div class="summary-card" style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);">
            <h3>My Students</h3>
            <p><?= count($studentList) ?></p>
        </div>
    </div>

    <!-- STUDENTS LIST -->
    <div class="student-list-card">
        <div class="student-list-header">👥 Assigned Students</div>
        <?php if (empty($studentList)): ?>
            <p class="no-data" style="padding:24px;">No students linked to your Mentor ID yet.</p>
        <?php else: ?>
            <?php foreach ($studentList as $st): ?>
            <div class="student-row">
                <div style="display:flex;align-items:center;">
                    <?php if (!empty($st['photo'])): ?>
                        <img src="<?= htmlspecialchars(imgUrl($st['photo'])) ?>" class="student-avatar" alt="">
                    <?php else: ?>
                        <div class="student-avatar-placeholder"><?= strtoupper(substr($st['name'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <div class="student-info">
                        <div class="s-name"><?= htmlspecialchars($st['name']) ?></div>
                        <div class="s-roll"><?= htmlspecialchars($st['roll']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($st['reg']) ?></div>
                        <div class="s-dept"><?= htmlspecialchars($st['dept']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($st['class']) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
