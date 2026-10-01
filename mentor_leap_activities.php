<?php
include 'config.php';
include 'leap_auth.php';
$m = requireMentorLeap();

// Create activity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $title    = trim($_POST['title'] ?? '');
    $type     = $_POST['type'] ?? 'OTHER';
    $desc     = trim($_POST['description'] ?? '');
    $start    = trim($_POST['start_datetime'] ?? '');
    $end      = trim($_POST['end_datetime'] ?? '');
    $reg      = isset($_POST['registration_required']) ? true : false;
    $deadline = trim($_POST['registration_deadline'] ?? '');

    if ($title && $start && in_array($type, ['TRAINING','MEETING','WORKSHOP','OTHER'])) {
        $leap_activities->insertOne([
            'mentor_id'              => $m['mentor_id'],
            'title'                  => $title,
            'type'                   => $type,
            'description'            => $desc,
            'start_datetime'         => new MongoDB\BSON\UTCDateTime(strtotime($start) * 1000),
            'end_datetime'           => $end ? new MongoDB\BSON\UTCDateTime(strtotime($end) * 1000) : null,
            'registration_required'  => $reg,
            'registration_deadline'  => $deadline,
            'created_by'             => $m['mentor_id'],
            'status'                 => 'ACTIVE',
            'created_at'             => new MongoDB\BSON\UTCDateTime(),
        ]);
        // Notify LEAP students
        $mems = $leap_memberships->find(['mentor_id' => $m['mentor_id'], 'status' => 'ACTIVE']);
        foreach ($mems as $mem) {
            leapNotifyStudent($mem['student_id'],
                "🗓 New LEAP activity: {$title} on " . date('d M Y', strtotime($start)),
                'leap_activities.php'
            );
        }
        header('Location: mentor_leap_activities.php?created=1');
        exit;
    }
}

// Delete / cancel
if (isset($_GET['delete'])) {
    $leap_activities->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($_GET['delete']), 'mentor_id' => $m['mentor_id']],
        ['$set' => ['status' => 'CANCELLED']]
    );
    header('Location: mentor_leap_activities.php');
    exit;
}

$activities = iterator_to_array($leap_activities->find(
    ['mentor_id' => $m['mentor_id']],
    ['sort' => ['start_datetime' => -1]]
));
$unreadCount = $notifications->countDocuments(['mentor_id' => $m['mentor_id'], 'read' => false]);
$typeColors  = ['TRAINING' => '#17a2b8', 'MEETING' => '#8e44ad', 'WORKSHOP' => '#f5a623', 'OTHER' => '#6c757d'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Activities (Mentor)</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<?php leapMentorNav($m, $unreadCount); ?>
<div class="container">
    <h2 style="color:#1a1a2e;margin-bottom:20px;">🗓 LEAP Activities</h2>

    <?php if (isset($_GET['created'])): ?><p class="success" style="margin-bottom:16px;">✅ Activity created and students notified.</p><?php endif; ?>

    <!-- Create form -->
    <div class="form-box" style="padding:24px;margin-bottom:28px;">
        <h3 style="color:#1a1a2e;margin-bottom:16px;">Create New Activity</h3>
        <form method="POST">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label>Title</label>
                    <input type="text" name="title" placeholder="Activity title" required>
                </div>
                <div>
                    <label>Type</label>
                    <select name="type">
                        <option value="TRAINING">Training</option>
                        <option value="MEETING">Meeting</option>
                        <option value="WORKSHOP">Workshop</option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>
                <div>
                    <label>Start Date &amp; Time</label>
                    <input type="datetime-local" name="start_datetime" required>
                </div>
                <div>
                    <label>End Date &amp; Time (optional)</label>
                    <input type="datetime-local" name="end_datetime">
                </div>
            </div>
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Activity details…"></textarea>
            <label style="display:flex;align-items:center;gap:8px;margin-top:10px;cursor:pointer;">
                <input type="checkbox" name="registration_required" style="width:auto;"> Registration required
            </label>
            <label>Registration Deadline (if required)</label>
            <input type="text" name="registration_deadline" placeholder="e.g. 25 Jan 2025">
            <button type="submit" name="create" class="btn-primary" style="margin-top:14px;">Create Activity</button>
        </form>
    </div>

    <!-- List -->
    <h3 style="color:#1a1a2e;margin-bottom:14px;">All Activities</h3>
    <?php if (empty($activities)): ?>
        <p class="no-data">No activities yet.</p>
    <?php else: ?>
        <?php foreach ($activities as $act):
            $color  = $typeColors[$act['type'] ?? 'OTHER'] ?? '#6c757d';
            $active = ($act['status'] ?? '') === 'ACTIVE';
        ?>
        <div style="background:#fff;border-radius:12px;padding:18px 22px;margin-bottom:14px;box-shadow:0 2px 8px rgba(0,0,0,0.07);border-left:4px solid <?= $color ?>;<?= !$active ? 'opacity:.6;' : '' ?>display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span style="font-size:16px;font-weight:700;color:#1a1a2e;"><?= htmlspecialchars($act['title']) ?></span>
                    <span style="background:<?= $color ?>;color:#fff;padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;"><?= htmlspecialchars($act['type']) ?></span>
                    <?php if (!$active): ?><span style="background:#e0e0e0;color:#888;padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;">CANCELLED</span><?php endif; ?>
                </div>
                <?php if (!empty($act['description'])): ?>
                <div style="font-size:13px;color:#666;margin-top:6px;"><?= htmlspecialchars($act['description']) ?></div>
                <?php endif; ?>
                <div style="font-size:13px;color:#888;margin-top:8px;">
                    📅 <?= date('d M Y, h:i A', $act['start_datetime']->toDateTime()->getTimestamp()) ?>
                    <?php if (!empty($act['end_datetime'])): ?>
                    → <?= date('h:i A', $act['end_datetime']->toDateTime()->getTimestamp()) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($active): ?>
            <a href="mentor_leap_activities.php?delete=<?= (string)$act['_id'] ?>"
               onclick="return confirm('Cancel this activity?')"
               style="color:#e94560;font-size:18px;text-decoration:none;flex-shrink:0;">🗑</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php leapMentorNotifJS(); leapFooter(); ?>
</body>
</html>
