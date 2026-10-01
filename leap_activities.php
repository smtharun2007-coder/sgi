<?php
include 'config.php';
requireLogin();
include 'leap_auth.php';
$mem = requireLeapMember();
$u   = $_SESSION['user'];

$activities = iterator_to_array($leap_activities->find(
    ['mentor_id' => $mem['mentor_id'], 'status' => 'ACTIVE'],
    ['sort' => ['start_datetime' => -1]]
));
$unreadCount = $notifications->countDocuments(['roll' => $u['roll'], 'read' => false]);

$typeColors = ['TRAINING' => '#17a2b8', 'MEETING' => '#8e44ad', 'WORKSHOP' => '#f5a623', 'OTHER' => '#6c757d'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Activities</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<?php leapStudentNav($u, $unreadCount); ?>
<div class="container">
    <h2 style="color:#1a1a2e;margin-bottom:20px;">🗓 LEAP Activities</h2>
    <?php if (empty($activities)): ?>
        <p class="no-data">No activities scheduled yet.</p>
    <?php else: ?>
        <?php foreach ($activities as $act):
            $color = $typeColors[$act['type'] ?? 'OTHER'] ?? '#6c757d';
        ?>
        <div style="background:#fff;border-radius:12px;padding:20px 24px;margin-bottom:16px;box-shadow:0 2px 10px rgba(0,0,0,0.07);border-left:4px solid <?= $color ?>;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                <div>
                    <div style="font-size:17px;font-weight:700;color:#1a1a2e;"><?= htmlspecialchars($act['title']) ?></div>
                    <div style="font-size:13px;color:#888;margin-top:4px;"><?= nl2br(htmlspecialchars($act['description'] ?? '')) ?></div>
                </div>
                <span style="background:<?= $color ?>;color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap;"><?= htmlspecialchars($act['type']) ?></span>
            </div>
            <div style="margin-top:12px;font-size:13px;color:#666;">
                📅 <?= date('d M Y, h:i A', $act['start_datetime']->toDateTime()->getTimestamp()) ?>
                <?php if (!empty($act['end_datetime'])): ?>
                → <?= date('h:i A', $act['end_datetime']->toDateTime()->getTimestamp()) ?>
                <?php endif; ?>
                <?php if (!empty($act['registration_required'])): ?>
                &nbsp;·&nbsp; <span style="color:#e94560;font-weight:600;">Registration required</span>
                <?php if (!empty($act['registration_deadline'])): ?>
                (by <?= htmlspecialchars($act['registration_deadline']) ?>)
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php leapNotifJS(); leapFooter(); ?>
</body>
</html>
