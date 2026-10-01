<?php
include 'config.php';
requireLogin();
include 'leap_auth.php';
$mem = requireLeapMember();
$u   = $_SESSION['user'];

// Fetch trainings for this mentor
$trainings = iterator_to_array($leap_training->find(
    ['mentor_id' => $mem['mentor_id']],
    ['sort' => ['created_at' => -1]]
));

$unreadCount = $notifications->countDocuments(['roll' => $u['roll'], 'read' => false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Training</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .session-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f2f5;font-size:13px;}
        .session-row:last-child{border-bottom:none;}
        .sess-badge{padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;color:#fff;}
        .sess-conducted{background:#28a745;}
        .sess-suspended{background:#f5a623;}
        .sess-cancelled{background:#e94560;}
        .sess-scheduled{background:#17a2b8;}
    </style>
</head>
<body>
<?php leapStudentNav($u, $unreadCount); ?>
<div class="container">
    <h2 style="color:#1a1a2e;margin-bottom:20px;">🎓 LEAP Training</h2>
    <?php if (empty($trainings)): ?>
        <p class="no-data">No training programs yet.</p>
    <?php else: ?>
        <?php foreach ($trainings as $tr):
            $sessions = iterator_to_array($leap_training_sessions->find(
                ['training_id' => (string)$tr['_id']],
                ['sort' => ['session_number' => 1]]
            ));
            // Attendance for this student
            $conductedSess = array_filter($sessions, fn($s) => ($s['status'] ?? '') === 'CONDUCTED');
            $conductedIds  = array_map(fn($s) => (string)$s['_id'], $conductedSess);
            $presentCnt = 0;
            if (!empty($conductedIds)) {
                $presentCnt = $leap_attendance->countDocuments([
                    'student_id' => $u['roll'],
                    'session_id' => ['$in' => $conductedIds],
                    'status'     => 'PRESENT',
                ]);
            }
            $conductedCnt = count($conductedSess);
            $pct = $conductedCnt > 0 ? round(($presentCnt / $conductedCnt) * 100) : null;
        ?>
        <div style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.07);margin-bottom:20px;">
            <div style="background:linear-gradient(135deg,#1a1a2e,#17a2b8);padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:16px;font-weight:700;color:#fff;"><?= htmlspecialchars($tr['title']) ?></div>
                    <div style="font-size:12px;color:rgba(255,255,255,0.7);margin-top:2px;"><?= count($sessions) ?> sessions</div>
                </div>
                <?php if ($pct !== null): ?>
                <div style="text-align:right;">
                    <div style="font-size:24px;font-weight:800;color:<?= $pct >= 75 ? '#a8ff78' : '#ff6b6b' ?>;"><?= $pct ?>%</div>
                    <div style="font-size:11px;color:rgba(255,255,255,0.7);"><?= $presentCnt ?>/<?= $conductedCnt ?> conducted</div>
                </div>
                <?php endif; ?>
            </div>
            <div style="padding:16px 20px;">
                <?php if (empty($sessions)): ?>
                <p class="no-data">No sessions added yet.</p>
                <?php else: ?>
                <?php foreach ($sessions as $sess):
                    $sessStatus = $sess['status'] ?? 'SCHEDULED';
                    $badgeClass = 'sess-' . strtolower($sessStatus);
                    // Get this student's attendance for this session
                    $attRec = null;
                    if ($sessStatus === 'CONDUCTED') {
                        $attRec = $leap_attendance->findOne(['session_id' => (string)$sess['_id'], 'student_id' => $u['roll']]);
                    }
                ?>
                <div class="session-row">
                    <div>
                        <strong>Session <?= $sess['session_number'] ?>:</strong> <?= htmlspecialchars($sess['title']) ?>
                        <?php if (!empty($sess['date'])): ?>
                        <span style="color:#888;font-size:12px;margin-left:8px;"><?= htmlspecialchars($sess['date']) ?></span>
                        <?php endif; ?>
                        <?php if ($sessStatus === 'SUSPENDED' && !empty($sess['suspension_reason'])): ?>
                        <div style="font-size:12px;color:#f5a623;margin-top:2px;">Reason: <?= htmlspecialchars($sess['suspension_reason']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <?php if ($attRec): ?>
                        <span style="font-size:12px;font-weight:700;color:<?= $attRec['status'] === 'PRESENT' ? '#28a745' : '#e94560' ?>;">
                            <?= $attRec['status'] ?>
                        </span>
                        <?php endif; ?>
                        <span class="sess-badge <?= $badgeClass ?>"><?= $sessStatus ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php leapNotifJS(); leapFooter(); ?>
</body>
</html>
