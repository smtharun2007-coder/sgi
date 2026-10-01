<?php
include 'config.php';
requireLogin();
include 'leap_auth.php';
$mem = requireLeapMember();
$u   = $_SESSION['user'];

// ── Register / unregister for an activity ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activity_id'], $_POST['reg_action'])) {
    $aid    = trim($_POST['activity_id']);
    $action = trim($_POST['reg_action']);
    try {
        $act = $leap_activities->findOne([
            '_id'       => new MongoDB\BSON\ObjectId($aid),
            'mentor_id' => $mem['mentor_id'],
            'status'    => 'ACTIVE',
        ]);
    } catch (Exception $e) { $act = null; }
    if ($act && !empty($act['registration_required'])) {
        if ($action === 'register') {
            $exists = $leap_activity_registrations->findOne(['activity_id' => $aid, 'student_id' => $u['roll']]);
            if (!$exists) {
                $leap_activity_registrations->insertOne([
                    'activity_id'   => $aid,
                    'mentor_id'     => $mem['mentor_id'],
                    'student_id'    => $u['roll'],
                    'student_name'  => $u['name'],
                    'registered_at' => new MongoDB\BSON\UTCDateTime(),
                ]);
                leapNotifyMentor($mem['mentor_id'],
                    "✅ {$u['name']} ({$u['roll']}) registered for activity \"{$act['title']}\"",
                    'mentor_leap_activities.php'
                );
            }
            header('Location: leap_activities.php?registered=1');
            exit;
        } elseif ($action === 'unregister') {
            $leap_activity_registrations->deleteOne(['activity_id' => $aid, 'student_id' => $u['roll']]);
            header('Location: leap_activities.php?unregistered=1');
            exit;
        }
    }
    header('Location: leap_activities.php');
    exit;
}

$activities = iterator_to_array($leap_activities->find(
    ['mentor_id' => $mem['mentor_id'], 'status' => 'ACTIVE'],
    ['sort' => ['start_datetime' => -1]]
));

// My registrations for quick lookup
$myRegs = [];
foreach (iterator_to_array($leap_activity_registrations->find(['student_id' => $u['roll']])) as $r) {
    $myRegs[$r['activity_id']] = true;
}
// Registration counts per activity
$regCounts = [];
foreach ($activities as $a) {
    $aid = (string)$a['_id'];
    if (!empty($a['registration_required'])) {
        $regCounts[$aid] = $leap_activity_registrations->countDocuments(['activity_id' => $aid]);
    }
}
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
    <?php if (isset($_GET['registered'])): ?><p class="success" style="margin-bottom:16px;">✅ Registered successfully.</p><?php endif; ?>
    <?php if (isset($_GET['unregistered'])): ?><p class="success" style="margin-bottom:16px;">Registration cancelled.</p><?php endif; ?>
    <?php if (empty($activities)): ?>
        <p class="no-data">No activities scheduled yet.</p>
    <?php else: ?>
        <?php foreach ($activities as $act):
            $color = $typeColors[$act['type'] ?? 'OTHER'] ?? '#6c757d';
            $aid = (string)$act['_id'];
            $needsReg = !empty($act['registration_required']);
            $isReg = isset($myRegs[$aid]);
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
                <?php if ($needsReg): ?>
                &nbsp;·&nbsp; <span style="color:#e94560;font-weight:600;">Registration required</span>
                <?php if (!empty($act['registration_deadline'])): ?>
                (by <?= htmlspecialchars($act['registration_deadline']) ?>)
                <?php endif; ?>
                &nbsp;·&nbsp; <span style="color:#1a1a2e;font-weight:600;">👥 <?= $regCounts[$aid] ?? 0 ?> registered</span>
                <?php endif; ?>
            </div>
            <?php if ($needsReg): ?>
            <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <?php if ($isReg): ?>
                    <span style="display:inline-block;padding:8px 18px;background:#eaffea;color:#28a745;border-radius:8px;font-size:13px;font-weight:700;">✅ Registered</span>
                    <form method="POST" style="display:inline;" onsubmit="return sgiConfirmFormSubmit(this,'Cancel your registration for this activity?','unreg_btn','Cancel Registration','Yes, Cancel')">
                        <input type="hidden" name="activity_id" value="<?= $aid ?>">
                        <input type="hidden" name="reg_action" value="unregister">
                        <button type="submit" name="unreg_btn" value="1" style="padding:8px 18px;background:#eee;color:#555;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;">Cancel Registration</button>
                    </form>
                <?php else: ?>
                    <form method="POST" style="display:inline;" onsubmit="return sgiConfirmFormSubmit(this,'Register for this activity?','reg_btn','Register','Yes, Register')">
                        <input type="hidden" name="activity_id" value="<?= $aid ?>">
                        <input type="hidden" name="reg_action" value="register">
                        <button type="submit" name="reg_btn" value="1" style="padding:9px 24px;background:linear-gradient(135deg,#1a1a2e,#f5a623);color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700;">📝 Register Now</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php leapNotifJS(); leapFooter(); ?>
</body>
</html>
