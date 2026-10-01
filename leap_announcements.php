<?php
include 'config.php';
requireLogin();
include 'leap_auth.php';
$mem = requireLeapMember();
$u   = $_SESSION['user'];

$anns = iterator_to_array($leap_announcements->find(
    ['mentor_id' => $mem['mentor_id'], 'status' => 'PUBLISHED'],
    ['sort' => ['created_at' => -1]]
));
$unreadCount = $notifications->countDocuments(['roll' => $u['roll'], 'read' => false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Announcements</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<?php leapStudentNav($u, $unreadCount); ?>
<div class="container">
    <h2 style="color:#1a1a2e;margin-bottom:20px;">📢 LEAP Announcements</h2>
    <?php if (empty($anns)): ?>
        <p class="no-data">No announcements yet.</p>
    <?php else: ?>
        <?php foreach ($anns as $a): ?>
        <div style="background:#fff;border-radius:12px;padding:20px 24px;margin-bottom:16px;box-shadow:0 2px 10px rgba(0,0,0,0.07);border-left:4px solid #f5a623;">
            <div style="font-size:17px;font-weight:700;color:#1a1a2e;margin-bottom:6px;"><?= htmlspecialchars($a['title']) ?></div>
            <div style="font-size:14px;color:#555;line-height:1.7;"><?= nl2br(htmlspecialchars($a['description'])) ?></div>
            <div style="font-size:12px;color:#aaa;margin-top:10px;">
                Posted by <?= htmlspecialchars($a['created_by_name'] ?? 'Mentor') ?> &nbsp;·&nbsp;
                <?= date('d M Y, h:i A', $a['created_at']->toDateTime()->getTimestamp()) ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php leapNotifJS(); leapFooter(); ?>
</body>
</html>
