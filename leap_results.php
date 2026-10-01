<?php
include 'config.php';
requireLogin();
include 'leap_auth.php';
$mem = requireLeapMember();
$u   = $_SESSION['user'];

// Only this student's own published results
$results = iterator_to_array($leap_test_results->find(
    ['student_id' => $u['roll'], 'status' => 'PUBLISHED'],
    ['sort' => ['published_at' => -1]]
));

$unreadCount = $notifications->countDocuments(['roll' => $u['roll'], 'read' => false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Results</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<?php leapStudentNav($u, $unreadCount); ?>
<div class="container">
    <h2 style="color:#1a1a2e;margin-bottom:20px;">🏆 My Results</h2>
    <?php if (empty($results)): ?>
        <p class="no-data">No results published yet.</p>
    <?php else: ?>
        <?php foreach ($results as $res):
            $test = $leap_tests->findOne(['_id' => new MongoDB\BSON\ObjectId($res['test_id'])]);
            $pct  = $res['maximum_marks'] > 0 ? round(($res['marks_obtained'] / $res['maximum_marks']) * 100) : 0;
            $color = $pct >= 75 ? '#28a745' : ($pct >= 50 ? '#f5a623' : '#e94560');
        ?>
        <div style="background:#fff;border-radius:12px;padding:20px 24px;margin-bottom:16px;box-shadow:0 2px 10px rgba(0,0,0,0.07);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
            <div>
                <div style="font-size:17px;font-weight:700;color:#1a1a2e;"><?= htmlspecialchars($test['title'] ?? 'Test') ?></div>
                <?php if (!empty($test['date'])): ?>
                <div style="font-size:13px;color:#888;margin-top:4px;">📅 <?= htmlspecialchars($test['date']) ?></div>
                <?php endif; ?>
                <?php if (!empty($res['remarks'])): ?>
                <div style="font-size:13px;color:#555;margin-top:6px;background:#f8f9fa;padding:8px 12px;border-radius:8px;">
                    💬 <?= htmlspecialchars($res['remarks']) ?>
                </div>
                <?php endif; ?>
                <div style="font-size:12px;color:#aaa;margin-top:8px;">
                    Published: <?= date('d M Y', $res['published_at']->toDateTime()->getTimestamp()) ?>
                </div>
            </div>
            <div style="text-align:center;min-width:100px;">
                <div style="font-size:36px;font-weight:800;color:<?= $color ?>;"><?= $res['marks_obtained'] ?></div>
                <div style="font-size:13px;color:#888;">/ <?= $res['maximum_marks'] ?></div>
                <div style="font-size:14px;font-weight:700;color:<?= $color ?>;margin-top:4px;"><?= $pct ?>%</div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php leapNotifJS(); leapFooter(); ?>
</body>
</html>
