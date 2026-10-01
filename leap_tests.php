<?php
include 'config.php';
requireLogin();
include 'leap_auth.php';
$mem = requireLeapMember();
$u   = $_SESSION['user'];

$tests = iterator_to_array($leap_tests->find(
    ['mentor_id' => $mem['mentor_id'], 'status' => ['$in' => ['UPCOMING','ONGOING','COMPLETED','PUBLISHED']]],
    ['sort' => ['date' => -1]]
));

$unreadCount = $notifications->countDocuments(['roll' => $u['roll'], 'read' => false]);

$statusColors = ['UPCOMING' => '#17a2b8', 'ONGOING' => '#f5a623', 'COMPLETED' => '#6c757d', 'PUBLISHED' => '#28a745'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Tests</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<?php leapStudentNav($u, $unreadCount); ?>
<div class="container">
    <h2 style="color:#1a1a2e;margin-bottom:20px;">📝 LEAP Tests</h2>
    <?php if (empty($tests)): ?>
        <p class="no-data">No tests scheduled yet.</p>
    <?php else: ?>
        <?php foreach ($tests as $test):
            $st    = $test['status'] ?? 'UPCOMING';
            $color = $statusColors[$st] ?? '#6c757d';
            // Check if result published for this student
            $myResult = null;
            if ($st === 'PUBLISHED') {
                $myResult = $leap_test_results->findOne([
                    'test_id'    => (string)$test['_id'],
                    'student_id' => $u['roll'],
                    'status'     => 'PUBLISHED',
                ]);
            }
        ?>
        <div style="background:#fff;border-radius:12px;padding:20px 24px;margin-bottom:16px;box-shadow:0 2px 10px rgba(0,0,0,0.07);border-left:4px solid <?= $color ?>;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
                <div>
                    <div style="font-size:17px;font-weight:700;color:#1a1a2e;"><?= htmlspecialchars($test['title']) ?></div>
                    <?php if (!empty($test['description'])): ?>
                    <div style="font-size:13px;color:#666;margin-top:4px;"><?= htmlspecialchars($test['description']) ?></div>
                    <?php endif; ?>
                    <div style="font-size:13px;color:#888;margin-top:8px;display:flex;flex-wrap:wrap;gap:16px;">
                        <?php if (!empty($test['date'])): ?>
                        <span>📅 <?= htmlspecialchars($test['date']) ?><?= !empty($test['start_time']) ? ' at ' . htmlspecialchars($test['start_time']) : '' ?></span>
                        <?php endif; ?>
                        <?php if (!empty($test['duration'])): ?>
                        <span>⏱ <?= htmlspecialchars($test['duration']) ?> mins</span>
                        <?php endif; ?>
                        <span>📊 Max: <?= htmlspecialchars($test['maximum_marks'] ?? '—') ?></span>
                    </div>
                    <?php if (!empty($test['instructions'])): ?>
                    <div style="font-size:12px;color:#888;margin-top:8px;background:#f8f9fa;padding:8px 12px;border-radius:8px;">
                        📋 <?= nl2br(htmlspecialchars($test['instructions'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="text-align:right;">
                    <span style="background:<?= $color ?>;color:#fff;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;"><?= $st ?></span>
                    <?php if ($myResult): ?>
                    <div style="margin-top:10px;background:#eaffea;border-radius:10px;padding:10px 16px;text-align:center;">
                        <div style="font-size:11px;color:#28a745;text-transform:uppercase;font-weight:700;">Your Score</div>
                        <div style="font-size:24px;font-weight:800;color:#1a1a2e;"><?= $myResult['marks_obtained'] ?><span style="font-size:14px;color:#888;">/ <?= $myResult['maximum_marks'] ?></span></div>
                        <?php if (!empty($myResult['remarks'])): ?>
                        <div style="font-size:12px;color:#555;margin-top:4px;"><?= htmlspecialchars($myResult['remarks']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php leapNotifJS(); leapFooter(); ?>
</body>
</html>
