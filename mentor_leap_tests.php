<?php
include 'config.php';
include 'leap_auth.php';
$m = requireMentorLeap();

// Create test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_test'])) {
    $title    = trim($_POST['title'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $date     = trim($_POST['date'] ?? '');
    $time     = trim($_POST['start_time'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $maxmarks = (int)($_POST['maximum_marks'] ?? 0);
    $instr    = trim($_POST['instructions'] ?? '');

    if ($title && $maxmarks > 0) {
        $leap_tests->insertOne([
            'mentor_id'     => $m['mentor_id'],
            'title'         => $title,
            'description'   => $desc,
            'date'          => $date,
            'start_time'    => $time,
            'duration'      => $duration,
            'maximum_marks' => $maxmarks,
            'instructions'  => $instr,
            'created_by'    => $m['mentor_id'],
            'status'        => 'UPCOMING',
            'created_at'    => new MongoDB\BSON\UTCDateTime(),
        ]);
        // Notify students
        $mems = $leap_memberships->find(['mentor_id' => $m['mentor_id'], 'status' => 'ACTIVE']);
        foreach ($mems as $mem) {
            leapNotifyStudent($mem['student_id'],
                "📝 New LEAP test scheduled: {$title}" . ($date ? " on $date" : ''),
                'leap_tests.php'
            );
        }
        header('Location: mentor_leap_tests.php?created=1');
        exit;
    }
}

// Update test status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $test_id = trim($_POST['test_id'] ?? '');
    $status  = trim($_POST['status'] ?? '');
    if ($test_id && in_array($status, ['UPCOMING','ONGOING','COMPLETED','PUBLISHED'])) {
        $leap_tests->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($test_id), 'mentor_id' => $m['mentor_id']],
            ['$set' => ['status' => $status]]
        );
    }
    header('Location: mentor_leap_tests.php');
    exit;
}

$tests = iterator_to_array($leap_tests->find(
    ['mentor_id' => $m['mentor_id']],
    ['sort' => ['created_at' => -1]]
));
$unreadCount = $notifications->countDocuments(['mentor_id' => $m['mentor_id'], 'read' => false]);
$statusColors = ['UPCOMING' => '#17a2b8', 'ONGOING' => '#f5a623', 'COMPLETED' => '#6c757d', 'PUBLISHED' => '#28a745'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Tests (Mentor)</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<?php leapMentorNav($m, $unreadCount); ?>
<div class="container">
    <h2 style="color:#1a1a2e;margin-bottom:20px;">📝 LEAP Tests</h2>

    <?php if (isset($_GET['created'])): ?><p class="success" style="margin-bottom:16px;">✅ Test created and students notified.</p><?php endif; ?>

    <!-- Create form -->
    <div class="form-box" style="padding:24px;margin-bottom:28px;">
        <h3 style="color:#1a1a2e;margin-bottom:16px;">Schedule New Test</h3>
        <form method="POST">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div><label>Title</label><input type="text" name="title" placeholder="e.g. Aptitude Test 1" required></div>
                <div><label>Maximum Marks</label><input type="number" name="maximum_marks" placeholder="e.g. 100" min="1" required></div>
                <div><label>Date</label><input type="date" name="date"></div>
                <div><label>Start Time</label><input type="time" name="start_time"></div>
                <div><label>Duration (minutes)</label><input type="number" name="duration" placeholder="e.g. 60"></div>
            </div>
            <label>Description</label>
            <textarea name="description" rows="2" placeholder="Brief description…"></textarea>
            <label>Instructions for students</label>
            <textarea name="instructions" rows="3" placeholder="e.g. Bring calculator, no phones…"></textarea>
            <button type="submit" name="create_test" class="btn-primary" style="margin-top:14px;">Schedule Test</button>
        </form>
    </div>

    <!-- Tests list -->
    <h3 style="color:#1a1a2e;margin-bottom:14px;">All Tests</h3>
    <?php if (empty($tests)): ?>
        <p class="no-data">No tests scheduled yet.</p>
    <?php else: ?>
        <?php foreach ($tests as $test):
            $st    = $test['status'] ?? 'UPCOMING';
            $color = $statusColors[$st] ?? '#6c757d';
        ?>
        <div style="background:#fff;border-radius:12px;padding:20px 24px;margin-bottom:14px;box-shadow:0 2px 8px rgba(0,0,0,0.07);border-left:4px solid <?= $color ?>;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
                <div>
                    <div style="font-size:17px;font-weight:700;color:#1a1a2e;"><?= htmlspecialchars($test['title']) ?></div>
                    <div style="font-size:13px;color:#888;margin-top:4px;display:flex;flex-wrap:wrap;gap:14px;">
                        <?php if (!empty($test['date'])): ?><span>📅 <?= htmlspecialchars($test['date']) ?></span><?php endif; ?>
                        <?php if (!empty($test['start_time'])): ?><span>🕐 <?= htmlspecialchars($test['start_time']) ?></span><?php endif; ?>
                        <?php if (!empty($test['duration'])): ?><span>⏱ <?= htmlspecialchars($test['duration']) ?> mins</span><?php endif; ?>
                        <span>📊 Max: <?= $test['maximum_marks'] ?></span>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                    <span style="background:<?= $color ?>;color:#fff;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;"><?= $st ?></span>
                    <form method="POST" style="display:flex;gap:6px;align-items:center;">
                        <input type="hidden" name="test_id" value="<?= (string)$test['_id'] ?>">
                        <select name="status" style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:12px;">
                            <option value="UPCOMING"  <?= $st==='UPCOMING'  ? 'selected':'' ?>>Upcoming</option>
                            <option value="ONGOING"   <?= $st==='ONGOING'   ? 'selected':'' ?>>Ongoing</option>
                            <option value="COMPLETED" <?= $st==='COMPLETED' ? 'selected':'' ?>>Completed</option>
                            <option value="PUBLISHED" <?= $st==='PUBLISHED' ? 'selected':'' ?>>Published</option>
                        </select>
                        <button type="submit" name="update_status" style="padding:6px 14px;background:#1a1a2e;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Update</button>
                    </form>
                    <a href="mentor_leap_results.php?test=<?= (string)$test['_id'] ?>" style="font-size:12px;color:#f5a623;text-decoration:none;font-weight:600;">Enter Results →</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php leapMentorNotifJS(); leapStudentDetailModal(); leapFooter(); ?>
</body>
</html>
