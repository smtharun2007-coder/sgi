<?php
include 'config.php';
requireLogin();
include 'leap_auth.php';

$u = $_SESSION['user'];

// Check membership
$membership = $leap_memberships->findOne(['student_id' => $u['roll'], 'status' => 'ACTIVE']);
if (!$membership) {
    header('Location: leap_apply.php');
    exit;
}

// Fetch mentor details
$mentorData = $mentors->findOne(['mentor_id' => $membership['mentor_id']]);

// Upcoming activities (next 5)
$now = new MongoDB\BSON\UTCDateTime();
$upcomingActivities = iterator_to_array($leap_activities->find(
    ['mentor_id' => $membership['mentor_id'], 'status' => 'ACTIVE', 'start_datetime' => ['$gte' => $now]],
    ['sort' => ['start_datetime' => 1], 'limit' => 3]
));

// Latest announcements
$latestAnnouncements = iterator_to_array($leap_announcements->find(
    ['mentor_id' => $membership['mentor_id'], 'status' => 'PUBLISHED'],
    ['sort' => ['created_at' => -1], 'limit' => 3]
));

// Upcoming tests
$upcomingTests = iterator_to_array($leap_tests->find(
    ['mentor_id' => $membership['mentor_id'], 'status' => ['$in' => ['UPCOMING', 'ONGOING']]],
    ['sort' => ['date' => 1], 'limit' => 3]
));

// Training attendance summary
$conductedSessions = iterator_to_array($leap_training_sessions->find([
    'mentor_id' => $membership['mentor_id'],
    'status'    => 'CONDUCTED',
]));
$conductedIds = array_map(fn($s) => (string)$s['_id'], $conductedSessions);
$presentCount = 0;
if (!empty($conductedIds)) {
    $presentCount = $leap_attendance->countDocuments([
        'student_id' => $u['roll'],
        'session_id' => ['$in' => $conductedIds],
        'status'     => 'PRESENT',
    ]);
}
$conductedCount = count($conductedSessions);
$attendancePct = $conductedCount > 0 ? round(($presentCount / $conductedCount) * 100) : null;

// Recent results
$recentResults = iterator_to_array($leap_test_results->find(
    ['student_id' => $u['roll'], 'status' => 'PUBLISHED'],
    ['sort' => ['published_at' => -1], 'limit' => 3]
));

// Fetch accepted application for coding profiles
$acceptedApp = $leap_applications->findOne([
    '_id' => new MongoDB\BSON\ObjectId($membership['application_id']),
]);
$leetcode   = $acceptedApp['leetcode_profile']   ?? '';
$hackerrank = $acceptedApp['hackerrank_profile']  ?? '';

$unreadCount = $notifications->countDocuments(['roll' => $u['roll'], 'read' => false]);
$pacc = $membership['pacc_level'] ?? null;
$year = ($u['year_from'] ?? '') . ' – ' . ($u['year_to'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Home</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .leap-banner{background:linear-gradient(135deg,#1a1a2e,#f5a623);border-radius:16px;padding:28px 32px;color:#fff;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
        .leap-banner h1{font-size:28px;font-weight:800;letter-spacing:2px;}
        .leap-banner p{opacity:.8;font-size:14px;margin-top:4px;}
        .pacc-badge{padding:8px 22px;border-radius:30px;font-size:14px;font-weight:800;letter-spacing:1px;color:#fff;}
        .pacc-elite{background:linear-gradient(135deg,#f5a623,#e67e22);}
        .pacc-super{background:linear-gradient(135deg,#8e44ad,#6c3483);}
        .pacc-advance{background:linear-gradient(135deg,#17a2b8,#117a8b);}
        .pacc-none{background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.4);}
        .banner-text{min-width:0;}
        .banner-text h1{overflow-wrap:anywhere;}
        .banner-text p{overflow-wrap:anywhere;word-break:break-word;}
        .profile-url{font-size:12px;color:#888;margin-top:2px;overflow-wrap:anywhere;word-break:break-all;max-width:100%;}
        .leap-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-bottom:24px;}
        .leap-card{background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.07);min-width:0;}
        .leap-card-header{padding:14px 20px;font-weight:700;font-size:14px;color:#fff;}
        .leap-card-body{padding:16px 20px;min-width:0;overflow-wrap:anywhere;}
        .mini-item{padding:10px 0;border-bottom:1px solid #f0f2f5;font-size:13px;color:#444;overflow-wrap:anywhere;}
        .mini-item:last-child{border-bottom:none;}
        .mini-item strong{color:#1a1a2e;overflow-wrap:anywhere;word-break:break-word;}
        .info-row{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid #f0f2f5;min-width:0;}
        .info-row:last-child{border-bottom:none;}
        .info-label{font-size:12px;color:#888;text-transform:uppercase;letter-spacing:.5px;flex-shrink:0;}
        .info-value{font-size:14px;font-weight:600;color:#1a1a2e;text-align:right;min-width:0;overflow-wrap:anywhere;word-break:break-word;}
        .profile-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:0 28px;min-width:0;}
        @media(max-width:640px){.leap-banner{padding:20px;}.leap-banner h1{font-size:22px;}.info-row{flex-direction:column;gap:2px;}.info-value{text-align:left;}}
    </style>
</head>
<body>
<?php leapStudentNav($u, $unreadCount, 'leap.php'); ?>
<div class="container">

    <!-- Banner -->
    <div class="leap-banner">
        <div style="display:flex;align-items:center;gap:16px;min-width:0;flex:1;">
            <img src="/LEAP.png" alt="LEAP" style="width:56px;height:56px;object-fit:contain;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,0.2);flex-shrink:0;">
            <div class="banner-text">
                <h1>LEAP</h1>
                <p>The Placement Series &nbsp;·&nbsp; Welcome, <?= htmlspecialchars($u['name']) ?></p>
            </div>
        </div>
        <div style="text-align:right;">
            <?php if ($pacc): ?>
                <div class="pacc-badge pacc-<?= strtolower($pacc) ?>">PACC: <?= htmlspecialchars($pacc) ?></div>
            <?php else: ?>
                <div class="pacc-badge pacc-none">PACC: Not Assigned</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Student Info -->
    <div class="leap-card" style="margin-bottom:24px;">
        <div class="leap-card-header" style="background:linear-gradient(135deg,#1a1a2e,#f5a623);">👤 My Profile</div>
        <div class="leap-card-body">
            <div class="profile-grid">
                <div class="info-row"><span class="info-label">Name</span><span class="info-value"><?= htmlspecialchars($u['name']) ?></span></div>
                <div class="info-row"><span class="info-label">Roll No</span><span class="info-value"><?= htmlspecialchars($u['roll']) ?></span></div>
                <div class="info-row"><span class="info-label">Department</span><span class="info-value"><?= htmlspecialchars($u['dept']) ?></span></div>
                <div class="info-row"><span class="info-label">Batch No</span><span class="info-value"><?= htmlspecialchars($u['batch_no'] ?? '—') ?></span></div>
                <div class="info-row"><span class="info-label">Year</span><span class="info-value"><?= htmlspecialchars($year) ?></span></div>
                <div class="info-row"><span class="info-label">PACC</span><span class="info-value"><?= $pacc ? htmlspecialchars($pacc) : 'Not Assigned' ?></span></div>
            </div>
        </div>
    </div>

    <div class="leap-grid">

        <!-- Mentor -->
        <div class="leap-card">
            <div class="leap-card-header" style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);">🧑‍🏫 My Mentor</div>
            <div class="leap-card-body">
                <?php if ($mentorData): ?>
                <div class="info-row"><span class="info-label">Name</span><span class="info-value"><?= htmlspecialchars($mentorData['name']) ?></span></div>
                <div class="info-row"><span class="info-label">Mentor ID</span><span class="info-value"><?= htmlspecialchars($mentorData['mentor_id']) ?></span></div>
                <div class="info-row"><span class="info-label">Department</span><span class="info-value"><?= htmlspecialchars($mentorData['dept'] ?? '—') ?></span></div>
                <div class="info-row"><span class="info-label">Email</span><span class="info-value" style="font-size:12px;"><?= htmlspecialchars($mentorData['email'] ?? '—') ?></span></div>
                <div class="info-row"><span class="info-label">Phone</span><span class="info-value"><?= htmlspecialchars($mentorData['phone'] ?? '—') ?></span></div>
                <?php else: ?>
                <p class="no-data">Mentor details unavailable.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Training Attendance -->
        <div class="leap-card">
            <div class="leap-card-header" style="background:linear-gradient(135deg,#1a1a2e,#17a2b8);">📊 Training Attendance</div>
            <div class="leap-card-body" style="text-align:center;padding:24px;">
                <?php if ($conductedCount > 0): ?>
                <div style="font-size:52px;font-weight:800;color:<?= $attendancePct >= 75 ? '#28a745' : '#e94560' ?>;"><?= $attendancePct ?>%</div>
                <div style="font-size:13px;color:#888;margin-top:6px;"><?= $presentCount ?> present / <?= $conductedCount ?> conducted sessions</div>
                <?php else: ?>
                <div style="color:#aaa;font-size:14px;padding:16px 0;">No sessions conducted yet.</div>
                <?php endif; ?>
                <a href="leap_progress.php" style="font-size:13px;color:#17a2b8;text-decoration:none;margin-top:12px;display:inline-block;">View full progress →</a>
            </div>
        </div>

        <!-- Announcements -->
        <div class="leap-card">
            <div class="leap-card-header" style="background:linear-gradient(135deg,#1a1a2e,#27ae60);">📢 Latest Announcements</div>
            <div class="leap-card-body">
                <?php if (empty($latestAnnouncements)): ?>
                <p class="no-data">No announcements yet.</p>
                <?php else: ?>
                <?php foreach ($latestAnnouncements as $ann): ?>
                <div class="mini-item"><strong><?= htmlspecialchars($ann['title']) ?></strong><br><span style="color:#888;font-size:12px;"><?= date('d M Y', $ann['created_at']->toDateTime()->getTimestamp()) ?></span></div>
                <?php endforeach; ?>
                <a href="leap_announcements.php" style="font-size:13px;color:#27ae60;text-decoration:none;margin-top:8px;display:inline-block;">View all →</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Activities -->
        <div class="leap-card">
            <div class="leap-card-header" style="background:linear-gradient(135deg,#1a1a2e,#e94560);">🗓 Upcoming Activities</div>
            <div class="leap-card-body">
                <?php if (empty($upcomingActivities)): ?>
                <p class="no-data">No upcoming activities.</p>
                <?php else: ?>
                <?php foreach ($upcomingActivities as $act): ?>
                <div class="mini-item">
                    <strong><?= htmlspecialchars($act['title']) ?></strong>
                    <span style="float:right;font-size:11px;background:#f0f2f5;padding:2px 8px;border-radius:10px;"><?= htmlspecialchars($act['type']) ?></span>
                    <br><span style="color:#888;font-size:12px;"><?= date('d M Y, h:i A', $act['start_datetime']->toDateTime()->getTimestamp()) ?></span>
                </div>
                <?php endforeach; ?>
                <a href="leap_activities.php" style="font-size:13px;color:#e94560;text-decoration:none;margin-top:8px;display:inline-block;">View all →</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Tests -->
        <div class="leap-card">
            <div class="leap-card-header" style="background:linear-gradient(135deg,#1a1a2e,#2980b9);">📝 Upcoming Tests</div>
            <div class="leap-card-body">
                <?php if (empty($upcomingTests)): ?>
                <p class="no-data">No upcoming tests.</p>
                <?php else: ?>
                <?php foreach ($upcomingTests as $test): ?>
                <div class="mini-item">
                    <strong><?= htmlspecialchars($test['title']) ?></strong><br>
                    <span style="color:#888;font-size:12px;"><?= htmlspecialchars($test['date'] ?? '') ?> &nbsp;·&nbsp; Max: <?= htmlspecialchars($test['maximum_marks'] ?? '—') ?></span>
                </div>
                <?php endforeach; ?>
                <a href="leap_tests.php" style="font-size:13px;color:#2980b9;text-decoration:none;margin-top:8px;display:inline-block;">View all →</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Coding Profiles -->
        <div class="leap-card">
            <div class="leap-card-header" style="background:linear-gradient(135deg,#1a1a2e,#2c3e50);">💻 My Coding Profiles</div>
            <div class="leap-card-body">
                <?php if (!$leetcode && !$hackerrank): ?>
                <p class="no-data">No coding profiles added.<br><a href="leap_profile_edit.php" style="color:#f5a623;font-size:13px;">Add profiles →</a></p>
                <?php else: ?>
                <?php if ($leetcode): ?>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid #f0f2f5;min-width:0;">
                    <div style="min-width:0;flex:1;">
                        <div style="font-size:13px;font-weight:700;color:#1a1a2e;">LeetCode</div>
                        <div class="profile-url"><?= htmlspecialchars($leetcode) ?></div>
                    </div>
                    <a href="<?= htmlspecialchars($leetcode) ?>" target="_blank" rel="noopener noreferrer"
                       style="flex-shrink:0;margin-left:12px;padding:7px 14px;background:#f5a623;color:#fff;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;">Open Profile</a>
                </div>
                <?php endif; ?>
                <?php if ($hackerrank): ?>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:12px 0;min-width:0;">
                    <div style="min-width:0;flex:1;">
                        <div style="font-size:13px;font-weight:700;color:#1a1a2e;">HackerRank</div>
                        <div class="profile-url"><?= htmlspecialchars($hackerrank) ?></div>
                    </div>
                    <a href="<?= htmlspecialchars($hackerrank) ?>" target="_blank" rel="noopener noreferrer"
                       style="flex-shrink:0;margin-left:12px;padding:7px 14px;background:#2ec866;color:#fff;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;">Open Profile</a>
                </div>
                <?php endif; ?>
                <div style="margin-top:10px;">
                    <a href="leap_profile_edit.php" style="font-size:12px;color:#888;text-decoration:none;">✏️ Edit profiles</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Results -->
        <div class="leap-card">
            <div class="leap-card-header" style="background:linear-gradient(135deg,#1a1a2e,#f39c12);">🏆 Recent Results</div>
            <div class="leap-card-body">
                <?php if (empty($recentResults)): ?>
                <p class="no-data">No results published yet.</p>
                <?php else: ?>
                <?php foreach ($recentResults as $res):
                    $test = $leap_tests->findOne(['_id' => new MongoDB\BSON\ObjectId($res['test_id'])]);
                ?>
                <div class="mini-item">
                    <strong><?= htmlspecialchars($test['title'] ?? 'Test') ?></strong>
                    <span style="float:right;font-weight:700;color:#f39c12;"><?= $res['marks_obtained'] ?>/<?= $res['maximum_marks'] ?></span>
                </div>
                <?php endforeach; ?>
                <a href="leap_results.php" style="font-size:13px;color:#f39c12;text-decoration:none;margin-top:8px;display:inline-block;">View all →</a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
<?php leapNotifJS(); leapFooter(); ?>
</body>
</html>
