<?php
include 'config.php';
include 'leap_auth.php';
$m = requireMentorLeap();

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalStudents  = $leap_memberships->countDocuments(['mentor_id' => $m['mentor_id'], 'status' => 'ACTIVE']);
$pendingApps    = $leap_applications->countDocuments(['mentor_id' => $m['mentor_id'], 'status' => 'PENDING']);
$totalTrainings = $leap_training->countDocuments(['mentor_id' => $m['mentor_id']]);
$paccUnassigned = $leap_memberships->countDocuments(['mentor_id' => $m['mentor_id'], 'status' => 'ACTIVE', 'pacc_level' => null]);

// Upcoming tests
$upcomingTests = $leap_tests->countDocuments(['mentor_id' => $m['mentor_id'], 'status' => ['$in' => ['UPCOMING', 'ONGOING']]]);

// Sessions to mark attendance: CONDUCTED but no attendance records yet
$conductedSessions = iterator_to_array($leap_training_sessions->find([
    'mentor_id' => $m['mentor_id'],
    'status'    => 'CONDUCTED',
]));
$needsAttendance = [];
foreach ($conductedSessions as $sess) {
    $attCount = $leap_attendance->countDocuments(['session_id' => (string)$sess['_id']]);
    if ($attCount === 0) {
        $tr = $leap_training->findOne(['_id' => new MongoDB\BSON\ObjectId($sess['training_id'])]);
        $needsAttendance[] = [
            'sess'     => $sess,
            'training' => $tr,
        ];
    }
}

// Recent pending applications
$recentApps = iterator_to_array($leap_applications->find(
    ['mentor_id' => $m['mentor_id'], 'status' => 'PENDING'],
    ['sort' => ['submitted_at' => -1], 'limit' => 3]
));

$unreadCount = $notifications->countDocuments(['mentor_id' => $m['mentor_id'], 'read' => false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEAP – Dashboard (Mentor)</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .leap-dash-hero {
            background: linear-gradient(135deg, #1a1a2e, #8e44ad);
            border-radius: 20px;
            padding: 32px 28px;
            color: #fff;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .leap-dash-hero img { width: 56px; height: 56px; border-radius: 12px; object-fit: contain; }
        .leap-dash-hero h1 { font-size: 26px; font-weight: 800; margin: 0 0 4px; }
        .leap-dash-hero p  { margin: 0; opacity: .8; font-size: 14px; }

        .stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-card {
            background: #fff; border-radius: 16px; padding: 20px 16px;
            text-align: center; box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            border-top: 4px solid transparent; transition: transform .2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card .num  { font-size: 36px; font-weight: 800; color: #1a1a2e; }
        .stat-card .lbl  { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }
        .stat-card.students  { border-top-color: #17a2b8; }
        .stat-card.pending   { border-top-color: #f5a623; }
        .stat-card.training  { border-top-color: #8e44ad; }
        .stat-card.pacc      { border-top-color: #e94560; }
        .stat-card.tests     { border-top-color: #28a745; }

        .dash-section { background: #fff; border-radius: 16px; padding: 24px 28px; margin-bottom: 20px; box-shadow: 0 4px 14px rgba(0,0,0,0.07); }
        .dash-section h3 { color: #1a1a2e; font-size: 16px; margin: 0 0 16px; padding-bottom: 10px; border-bottom: 2px solid #f0f2f5; display: flex; justify-content: space-between; align-items: center; }
        .dash-section h3 a { font-size: 12px; color: #8e44ad; font-weight: 600; text-decoration: none; }

        .app-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f0f2f5; gap: 12px; flex-wrap: wrap; }
        .app-row:last-child { border-bottom: none; }

        .att-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f0f2f5; gap: 12px; flex-wrap: wrap; }
        .att-row:last-child { border-bottom: none; }

        .quick-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 28px; }
        .qa-btn {
            flex: 1; min-width: 140px; padding: 16px 12px; border-radius: 14px;
            text-align: center; font-size: 13px; font-weight: 700; text-decoration: none;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .qa-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .qa-btn .icon { font-size: 24px; display: block; margin-bottom: 6px; }
        .qa-btn.apps   { background: linear-gradient(135deg,#f5a623,#e67e22); color:#fff; }
        .qa-btn.att    { background: linear-gradient(135deg,#28a745,#1e7e34); color:#fff; }
        .qa-btn.tests  { background: linear-gradient(135deg,#17a2b8,#117a8b); color:#fff; }
        .qa-btn.pacc   { background: linear-gradient(135deg,#8e44ad,#6c3483); color:#fff; }
        .qa-btn.ann    { background: linear-gradient(135deg,#1a1a2e,#2c2c54); color:#fff; }
    </style>
</head>
<body>
<?php leapMentorNav($m, $unreadCount); ?>
<div class="container">

    <!-- Hero -->
    <div class="leap-dash-hero">
        <img src="/LEAP.png" alt="LEAP">
        <div>
            <h1>🏠 LEAP Dashboard</h1>
            <p>Welcome, <?= htmlspecialchars($m['name']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($m['dept'] ?? '') ?></p>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
        <div class="stat-card students">
            <div class="num"><?= $totalStudents ?></div>
            <div class="lbl">Active Students</div>
        </div>
        <div class="stat-card pending">
            <div class="num"><?= $pendingApps ?></div>
            <div class="lbl">Pending Applications</div>
        </div>
        <div class="stat-card training">
            <div class="num"><?= $totalTrainings ?></div>
            <div class="lbl">Training Programs</div>
        </div>
        <div class="stat-card tests">
            <div class="num"><?= $upcomingTests ?></div>
            <div class="lbl">Active Tests</div>
        </div>
        <div class="stat-card pacc">
            <div class="num"><?= $paccUnassigned ?></div>
            <div class="lbl">PACC Unassigned</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="mentor_leap_applications.php" class="qa-btn apps">
            <span class="icon">📋</span>Applications
            <?php if ($pendingApps > 0): ?>
            <span style="display:inline-block;margin-top:4px;background:rgba(0,0,0,0.2);border-radius:10px;padding:1px 8px;font-size:11px;"><?= $pendingApps ?> pending</span>
            <?php endif; ?>
        </a>
        <a href="mentor_leap_attendance.php" class="qa-btn att">
            <span class="icon">✅</span>Attendance
            <?php if (count($needsAttendance) > 0): ?>
            <span style="display:inline-block;margin-top:4px;background:rgba(0,0,0,0.2);border-radius:10px;padding:1px 8px;font-size:11px;"><?= count($needsAttendance) ?> pending</span>
            <?php endif; ?>
        </a>
        <a href="mentor_leap_tests.php" class="qa-btn tests">
            <span class="icon">📝</span>Tests
        </a>
        <a href="mentor_leap_pacc.php" class="qa-btn pacc">
            <span class="icon">⭐</span>PACC
            <?php if ($paccUnassigned > 0): ?>
            <span style="display:inline-block;margin-top:4px;background:rgba(0,0,0,0.2);border-radius:10px;padding:1px 8px;font-size:11px;"><?= $paccUnassigned ?> unassigned</span>
            <?php endif; ?>
        </a>
        <a href="mentor_leap_announcements.php" class="qa-btn ann">
            <span class="icon">📢</span>Announcements
        </a>
    </div>

    <!-- Sessions needing attendance -->
    <?php if (!empty($needsAttendance)): ?>
    <div class="dash-section">
        <h3>⚠️ Attendance Not Marked <a href="mentor_leap_attendance.php">View All →</a></h3>
        <?php foreach ($needsAttendance as $item):
            $sess = $item['sess'];
            $tr   = $item['training'];
        ?>
        <div class="att-row">
            <div>
                <div style="font-weight:700;color:#1a1a2e;font-size:14px;"><?= htmlspecialchars($sess['title']) ?></div>
                <div style="font-size:12px;color:#888;margin-top:2px;">
                    <?= htmlspecialchars($tr['title'] ?? '') ?>
                    <?php if (!empty($sess['date'])): ?> · 📅 <?= htmlspecialchars($sess['date']) ?><?php endif; ?>
                </div>
            </div>
            <a href="mentor_leap_attendance.php?tr=<?= htmlspecialchars($sess['training_id']) ?>&sess=<?= (string)$sess['_id'] ?>"
               style="padding:7px 18px;background:#28a745;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap;">
                Mark Now →
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Recent pending applications -->
    <?php if (!empty($recentApps)): ?>
    <div class="dash-section">
        <h3>⏳ Pending Applications <a href="mentor_leap_applications.php">View All →</a></h3>
        <?php foreach ($recentApps as $app): ?>
        <div class="app-row">
            <div>
                <div style="font-weight:700;color:#1a1a2e;font-size:14px;"><?= htmlspecialchars($app['name']) ?></div>
                <div style="font-size:12px;color:#888;margin-top:2px;">
                    <?= htmlspecialchars($app['roll_no']) ?> · <?= htmlspecialchars($app['department']) ?>
                    · Submitted <?= date('d M Y, h:i A', $app['submitted_at']->toDateTime()->getTimestamp()) ?>
                </div>
            </div>
            <a href="mentor_leap_applications.php"
               style="padding:7px 18px;background:#f5a623;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap;">
                Review →
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- All clear message -->
    <?php if (empty($needsAttendance) && empty($recentApps)): ?>
    <div class="dash-section" style="text-align:center;padding:40px;">
        <div style="font-size:48px;margin-bottom:12px;">🎉</div>
        <h3 style="border:none;justify-content:center;font-size:18px;">All caught up!</h3>
        <p style="color:#888;font-size:14px;">No pending applications or attendance to mark.</p>
    </div>
    <?php endif; ?>

</div>
<?php leapMentorNotifJS(); leapStudentDetailModal(); leapFooter(); ?>
</body>
</html>
