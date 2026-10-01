<?php
include 'config.php';
requireLogin();
include 'leap_auth.php';
$mem = requireLeapMember();
$u   = $_SESSION['user'];

$unreadCount = $notifications->countDocuments(['roll' => $u['roll'], 'read' => false]);
$pacc = $mem['pacc_level'] ?? null;

// ── Training attendance (per training, suspended excluded) ───────────────────
$trainings = iterator_to_array($leap_training->find(
    ['mentor_id' => $mem['mentor_id']],
    ['sort' => ['created_at' => 1]]
));

$trainingStats = [];
$totalConducted = 0;
$totalPresent   = 0;

foreach ($trainings as $tr) {
    $sessions = iterator_to_array($leap_training_sessions->find(
        ['training_id' => (string)$tr['_id']],
        ['sort' => ['session_number' => 1]]
    ));
    $conducted = array_filter($sessions, fn($s) => ($s['status'] ?? '') === 'CONDUCTED');
    $suspended = array_filter($sessions, fn($s) => ($s['status'] ?? '') === 'SUSPENDED');
    $conductedIds = array_map(fn($s) => (string)$s['_id'], $conducted);

    $present = 0;
    if (!empty($conductedIds)) {
        $present = $leap_attendance->countDocuments([
            'student_id' => $u['roll'],
            'session_id' => ['$in' => $conductedIds],
            'status'     => 'PRESENT',
        ]);
    }
    $cCnt = count($conducted);
    $pct  = $cCnt > 0 ? round(($present / $cCnt) * 100) : null;

    $totalConducted += $cCnt;
    $totalPresent   += $present;

    $trainingStats[] = [
        'title'     => $tr['title'],
        'total'     => count($sessions),
        'conducted' => $cCnt,
        'suspended' => count($suspended),
        'present'   => $present,
        'absent'    => $cCnt - $present,
        'pct'       => $pct,
    ];
}

$overallPct = $totalConducted > 0 ? round(($totalPresent / $totalConducted) * 100) : null;

// ── Activities ───────────────────────────────────────────────────────────────
$activityCount = $leap_activities->countDocuments([
    'mentor_id' => $mem['mentor_id'],
    'status'    => 'ACTIVE',
]);

// ── Tests & Results ──────────────────────────────────────────────────────────
$allResults = iterator_to_array($leap_test_results->find(
    ['student_id' => $u['roll'], 'status' => 'PUBLISHED'],
    ['sort' => ['published_at' => -1]]
));

$totalMarks = 0;
$totalMax   = 0;
foreach ($allResults as $r) {
    $totalMarks += (int)($r['marks_obtained'] ?? 0);
    $totalMax   += (int)($r['maximum_marks']  ?? 0);
}
$avgPct = $totalMax > 0 ? round(($totalMarks / $totalMax) * 100) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – My Progress</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .prog-card{background:#fff;border-radius:14px;padding:24px;box-shadow:0 2px 10px rgba(0,0,0,0.07);margin-bottom:20px;}
        .prog-card h3{color:#1a1a2e;font-size:16px;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid #f0f2f5;}
        .stat-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f2f5;font-size:14px;}
        .stat-row:last-child{border-bottom:none;}
        .stat-label{color:#666;}
        .stat-val{font-weight:700;color:#1a1a2e;}
        .big-pct{font-size:48px;font-weight:800;text-align:center;padding:16px 0;}
        .progress-bar-wrap{background:#f0f2f5;border-radius:20px;height:10px;overflow:hidden;margin-top:6px;}
        .progress-bar-fill{height:100%;border-radius:20px;transition:width .4s;}
        .pacc-display{text-align:center;padding:20px;border-radius:12px;margin-bottom:20px;}
        .pacc-elite{background:linear-gradient(135deg,#f5a623,#e67e22);color:#fff;}
        .pacc-super{background:linear-gradient(135deg,#8e44ad,#6c3483);color:#fff;}
        .pacc-advance{background:linear-gradient(135deg,#17a2b8,#117a8b);color:#fff;}
        .pacc-none{background:#f8f9fa;color:#888;border:2px dashed #ddd;}
    </style>
</head>
<body>
<?php leapStudentNav($u, $unreadCount); ?>
<div class="container">
    <h2 style="color:#1a1a2e;margin-bottom:20px;">📈 My Progress</h2>

    <!-- PACC -->
    <div class="pacc-display <?= $pacc ? 'pacc-' . strtolower($pacc) : 'pacc-none' ?>">
        <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;opacity:.8;margin-bottom:6px;">PACC Level</div>
        <div style="font-size:32px;font-weight:800;"><?= $pacc ?? 'Not Assigned' ?></div>
        <?php if (!$pacc): ?>
        <div style="font-size:13px;margin-top:6px;">Your mentor will assign your PACC level.</div>
        <?php endif; ?>
    </div>

    <!-- Overall Training Attendance -->
    <div class="prog-card">
        <h3>🎓 Training Attendance</h3>
        <?php if ($overallPct !== null): ?>
        <div class="big-pct" style="color:<?= $overallPct >= 75 ? '#28a745' : '#e94560' ?>;"><?= $overallPct ?>%</div>
        <div style="text-align:center;font-size:13px;color:#888;margin-bottom:16px;">
            <?= $totalPresent ?> present / <?= $totalConducted ?> conducted sessions overall
        </div>
        <?php else: ?>
        <p class="no-data" style="text-align:center;">No sessions conducted yet.</p>
        <?php endif; ?>

        <?php foreach ($trainingStats as $ts): ?>
        <div style="margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                <span style="font-size:14px;font-weight:600;color:#1a1a2e;"><?= htmlspecialchars($ts['title']) ?></span>
                <span style="font-size:14px;font-weight:700;color:<?= ($ts['pct'] ?? 0) >= 75 ? '#28a745' : '#e94560' ?>;">
                    <?= $ts['pct'] !== null ? $ts['pct'] . '%' : '—' ?>
                </span>
            </div>
            <?php if ($ts['pct'] !== null): ?>
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill" style="width:<?= $ts['pct'] ?>%;background:<?= $ts['pct'] >= 75 ? '#28a745' : '#e94560' ?>;"></div>
            </div>
            <?php endif; ?>
            <div style="font-size:12px;color:#aaa;margin-top:4px;display:flex;gap:16px;">
                <span>✅ Present: <?= $ts['present'] ?></span>
                <span>❌ Absent: <?= $ts['absent'] ?></span>
                <span>⏸ Suspended: <?= $ts['suspended'] ?> <em>(excluded from %)</em></span>
                <span>Total sessions: <?= $ts['total'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Activities -->
    <div class="prog-card">
        <h3>🗓 Activities</h3>
        <div class="stat-row">
            <span class="stat-label">Total LEAP Activities</span>
            <span class="stat-val"><?= $activityCount ?></span>
        </div>
        <div style="margin-top:10px;"><a href="leap_activities.php" style="font-size:13px;color:#e94560;text-decoration:none;">View activities →</a></div>
    </div>

    <!-- Tests & Results -->
    <div class="prog-card">
        <h3>📝 Tests & Results</h3>
        <?php if (empty($allResults)): ?>
        <p class="no-data">No results published yet.</p>
        <?php else: ?>
        <div class="stat-row">
            <span class="stat-label">Tests with published results</span>
            <span class="stat-val"><?= count($allResults) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Overall average score</span>
            <span class="stat-val" style="color:<?= ($avgPct ?? 0) >= 75 ? '#28a745' : '#e94560' ?>;"><?= $avgPct !== null ? $avgPct . '%' : '—' ?></span>
        </div>
        <?php if ($avgPct !== null): ?>
        <div class="progress-bar-wrap" style="margin-top:10px;">
            <div class="progress-bar-fill" style="width:<?= $avgPct ?>%;background:<?= $avgPct >= 75 ? '#28a745' : '#e94560' ?>;"></div>
        </div>
        <?php endif; ?>
        <div style="margin-top:12px;">
            <?php foreach ($allResults as $r):
                $test = $leap_tests->findOne(['_id' => new MongoDB\BSON\ObjectId($r['test_id'])]);
                $p = $r['maximum_marks'] > 0 ? round(($r['marks_obtained'] / $r['maximum_marks']) * 100) : 0;
            ?>
            <div class="stat-row">
                <span class="stat-label"><?= htmlspecialchars($test['title'] ?? 'Test') ?></span>
                <span class="stat-val" style="color:<?= $p >= 75 ? '#28a745' : ($p >= 50 ? '#f5a623' : '#e94560') ?>;">
                    <?= $r['marks_obtained'] ?>/<?= $r['maximum_marks'] ?> (<?= $p ?>%)
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:10px;"><a href="leap_results.php" style="font-size:13px;color:#f39c12;text-decoration:none;">View all results →</a></div>
        <?php endif; ?>
    </div>

</div>
<?php leapNotifJS(); leapFooter(); ?>
</body>
</html>
