<?php
include 'config.php';
include 'leap_auth.php';
$m = requireMentorLeap();

// Create training program
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_training'])) {
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    if ($title) {
        $leap_training->insertOne([
            'mentor_id'   => $m['mentor_id'],
            'title'       => $title,
            'description' => $desc,
            'created_by'  => $m['mentor_id'],
            'created_at'  => new MongoDB\BSON\UTCDateTime(),
        ]);
        header('Location: mentor_leap_training.php?created=1');
        exit;
    }
}

// Add session to a training
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_session'])) {
    $training_id = trim($_POST['training_id'] ?? '');
    $title       = trim($_POST['session_title'] ?? '');
    $desc        = trim($_POST['session_desc'] ?? '');
    $date        = trim($_POST['session_date'] ?? '');
    $start_time  = trim($_POST['start_time'] ?? '');
    $end_time    = trim($_POST['end_time'] ?? '');

    // Verify training belongs to this mentor
    $tr = $leap_training->findOne(['_id' => new MongoDB\BSON\ObjectId($training_id), 'mentor_id' => $m['mentor_id']]);
    if ($tr && $title) {
        // Get next session number
        $lastSess = $leap_training_sessions->findOne(
            ['training_id' => $training_id],
            ['sort' => ['session_number' => -1]]
        );
        $nextNum = ($lastSess ? (int)$lastSess['session_number'] : 0) + 1;

        $leap_training_sessions->insertOne([
            'training_id'    => $training_id,
            'mentor_id'      => $m['mentor_id'],
            'session_number' => $nextNum,
            'title'          => $title,
            'description'    => $desc,
            'date'           => $date,
            'start_time'     => $start_time,
            'end_time'       => $end_time,
            'status'         => 'SCHEDULED',
            'suspension_reason' => '',
            'created_at'     => new MongoDB\BSON\UTCDateTime(),
        ]);
        // Notify LEAP students
        $mems = $leap_memberships->find(['mentor_id' => $m['mentor_id'], 'status' => 'ACTIVE']);
        foreach ($mems as $mem) {
            leapNotifyStudent($mem['student_id'],
                "📚 New LEAP training session added: {$title}" . ($date ? " on $date" : ''),
                'leap_training.php'
            );
        }
        header('Location: mentor_leap_training.php?tr=' . $training_id . '&added=1');
        exit;
    }
}

// Update session status (CONDUCTED / SUSPENDED / CANCELLED)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_session'])) {
    $sess_id = trim($_POST['session_id'] ?? '');
    $status  = trim($_POST['status'] ?? '');
    $reason  = trim($_POST['suspension_reason'] ?? '');

    if ($sess_id && in_array($status, ['SCHEDULED','CONDUCTED','SUSPENDED','CANCELLED'])) {
        $sess = $leap_training_sessions->findOne([
            '_id'       => new MongoDB\BSON\ObjectId($sess_id),
            'mentor_id' => $m['mentor_id'],
        ]);
        if ($sess) {
            $update = ['status' => $status];
            if ($status === 'SUSPENDED') {
                if (!$reason) {
                    header('Location: mentor_leap_training.php?tr=' . $sess['training_id'] . '&err=reason');
                    exit;
                }
                $update['suspension_reason'] = $reason;
                // Notify students — suspended session
                $mems = $leap_memberships->find(['mentor_id' => $m['mentor_id'], 'status' => 'ACTIVE']);
                foreach ($mems as $mem) {
                    leapNotifyStudent($mem['student_id'],
                        "⏸ LEAP training session \"{$sess['title']}\" has been suspended. Reason: {$reason}",
                        'leap_training.php'
                    );
                }
            }
            $leap_training_sessions->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($sess_id)],
                ['$set' => $update]
            );
            header('Location: mentor_leap_training.php?tr=' . $sess['training_id'] . '&updated=1');
            exit;
        }
    }
}

$trainings = iterator_to_array($leap_training->find(
    ['mentor_id' => $m['mentor_id']],
    ['sort' => ['created_at' => -1]]
));

// Active training for session view
$activeTrId = $_GET['tr'] ?? (count($trainings) > 0 ? (string)$trainings[0]['_id'] : null);
$activeTr   = null;
$sessions   = [];
if ($activeTrId) {
    $activeTr = $leap_training->findOne(['_id' => new MongoDB\BSON\ObjectId($activeTrId), 'mentor_id' => $m['mentor_id']]);
    if ($activeTr) {
        $sessions = iterator_to_array($leap_training_sessions->find(
            ['training_id' => $activeTrId],
            ['sort' => ['session_number' => 1]]
        ));
    }
}

$unreadCount = $notifications->countDocuments(['mentor_id' => $m['mentor_id'], 'read' => false]);
$statusColors = ['SCHEDULED' => '#17a2b8', 'CONDUCTED' => '#28a745', 'SUSPENDED' => '#f5a623', 'CANCELLED' => '#e94560'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Training (Mentor)</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .tr-tab{padding:10px 18px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;border:2px solid #e0e0e0;background:#fff;color:#555;transition:all .2s;}
        .tr-tab.active{background:linear-gradient(135deg,#1a1a2e,#17a2b8);color:#fff;border-color:transparent;}
        .sess-row{display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid #f0f2f5;}
        .sess-row:last-child{border-bottom:none;}
        .sess-num{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1a1a2e,#17a2b8);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;}
    </style>
</head>
<body>
<?php leapMentorNav($m, $unreadCount); ?>
<div class="container">
    <h2 style="color:#1a1a2e;margin-bottom:20px;">🎓 LEAP Training Management</h2>

    <?php if (isset($_GET['created'])): ?><p class="success" style="margin-bottom:12px;">✅ Training program created.</p><?php endif; ?>
    <?php if (isset($_GET['added'])): ?><p class="success" style="margin-bottom:12px;">✅ Session added and students notified.</p><?php endif; ?>
    <?php if (isset($_GET['updated'])): ?><p class="success" style="margin-bottom:12px;">✅ Session status updated.</p><?php endif; ?>
    <?php if (isset($_GET['err']) && $_GET['err'] === 'reason'): ?><p class="error" style="margin-bottom:12px;">A suspension reason is required.</p><?php endif; ?>

    <div style="display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:flex-start;">

        <!-- Left: Training list + create -->
        <div>
            <div class="form-box" style="padding:20px;margin-bottom:16px;">
                <h3 style="color:#1a1a2e;margin-bottom:14px;font-size:15px;">New Training Program</h3>
                <form method="POST">
                    <input type="text" name="title" placeholder="Training title" required>
                    <textarea name="description" rows="2" placeholder="Description (optional)" style="margin-top:8px;"></textarea>
                    <button type="submit" name="create_training" class="btn-primary" style="margin-top:10px;padding:10px;">Create</button>
                </form>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <?php foreach ($trainings as $tr): ?>
                <a href="mentor_leap_training.php?tr=<?= (string)$tr['_id'] ?>"
                   class="tr-tab <?= $activeTrId === (string)$tr['_id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($tr['title']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right: Sessions -->
        <div>
            <?php if ($activeTr): ?>
            <div class="form-box" style="padding:24px;margin-bottom:20px;">
                <h3 style="color:#1a1a2e;margin-bottom:4px;"><?= htmlspecialchars($activeTr['title']) ?></h3>
                <?php if (!empty($activeTr['description'])): ?>
                <p style="color:#888;font-size:13px;margin-bottom:16px;"><?= htmlspecialchars($activeTr['description']) ?></p>
                <?php endif; ?>

                <!-- Add session form -->
                <details style="margin-bottom:20px;">
                    <summary style="cursor:pointer;font-weight:700;color:#1a1a2e;padding:10px 0;">+ Add New Session</summary>
                    <form method="POST" style="margin-top:12px;">
                        <input type="hidden" name="training_id" value="<?= $activeTrId ?>">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <div><label>Session Title</label><input type="text" name="session_title" placeholder="e.g. Aptitude Basics" required></div>
                            <div><label>Date</label><input type="date" name="session_date"></div>
                            <div><label>Start Time</label><input type="time" name="start_time"></div>
                            <div><label>End Time</label><input type="time" name="end_time"></div>
                        </div>
                        <label>Description</label>
                        <textarea name="session_desc" rows="2" placeholder="Session details…"></textarea>
                        <button type="submit" name="add_session" class="btn-primary" style="margin-top:10px;padding:10px 24px;width:auto;">Add Session</button>
                    </form>
                </details>

                <!-- Sessions list -->
                <?php if (empty($sessions)): ?>
                <p class="no-data">No sessions yet. Add the first session above.</p>
                <?php else: ?>
                <?php foreach ($sessions as $sess):
                    $st    = $sess['status'] ?? 'SCHEDULED';
                    $color = $statusColors[$st] ?? '#6c757d';
                ?>
                <div class="sess-row">
                    <div class="sess-num"><?= $sess['session_number'] ?></div>
                    <div>
                        <div style="font-weight:700;color:#1a1a2e;font-size:14px;"><?= htmlspecialchars($sess['title']) ?></div>
                        <div style="font-size:12px;color:#888;margin-top:2px;">
                            <?= !empty($sess['date']) ? '📅 ' . htmlspecialchars($sess['date']) : '' ?>
                            <?= !empty($sess['start_time']) ? ' · ' . htmlspecialchars($sess['start_time']) : '' ?>
                            <?= !empty($sess['end_time']) ? ' – ' . htmlspecialchars($sess['end_time']) : '' ?>
                        </div>
                        <?php if ($st === 'SUSPENDED' && !empty($sess['suspension_reason'])): ?>
                        <div style="font-size:12px;color:#f5a623;margin-top:2px;">⏸ <?= htmlspecialchars($sess['suspension_reason']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
                        <span style="background:<?= $color ?>;color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;"><?= $st ?></span>
                        <!-- Status update form -->
                        <form method="POST" style="display:flex;gap:6px;align-items:center;">
                            <input type="hidden" name="session_id" value="<?= (string)$sess['_id'] ?>">
                            <select name="status" style="padding:5px 8px;border:1px solid #ddd;border-radius:6px;font-size:12px;" onchange="toggleSuspendReason(this,'sr_<?= (string)$sess['_id'] ?>')">
                                <option value="SCHEDULED"  <?= $st==='SCHEDULED'  ? 'selected':'' ?>>Scheduled</option>
                                <option value="CONDUCTED"  <?= $st==='CONDUCTED'  ? 'selected':'' ?>>Conducted</option>
                                <option value="SUSPENDED"  <?= $st==='SUSPENDED'  ? 'selected':'' ?>>Suspended</option>
                                <option value="CANCELLED"  <?= $st==='CANCELLED'  ? 'selected':'' ?>>Cancelled</option>
                            </select>
                            <button type="submit" name="update_session" style="padding:5px 12px;background:#1a1a2e;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Save</button>
                        </form>
                        <div id="sr_<?= (string)$sess['_id'] ?>" style="display:<?= $st==='SUSPENDED'?'block':'none' ?>;width:100%;">
                            <form method="POST" style="display:flex;gap:6px;align-items:center;margin-top:4px;">
                                <input type="hidden" name="session_id" value="<?= (string)$sess['_id'] ?>">
                                <input type="hidden" name="status" value="SUSPENDED">
                                <input type="text" name="suspension_reason" placeholder="Suspension reason (required)" value="<?= htmlspecialchars($sess['suspension_reason'] ?? '') ?>" style="font-size:12px;padding:5px 8px;flex:1;margin:0;">
                                <button type="submit" name="update_session" style="padding:5px 12px;background:#f5a623;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px;white-space:nowrap;">Save Reason</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="background:#fff;border-radius:14px;padding:40px;text-align:center;color:#aaa;box-shadow:0 2px 10px rgba(0,0,0,0.07);">
                Create a training program to get started.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php leapMentorNotifJS(); leapStudentDetailModal(); leapFooter(); ?>
<script>
function toggleSuspendReason(sel, divId) {
    document.getElementById(divId).style.display = sel.value === 'SUSPENDED' ? 'block' : 'none';
}
</script>
</body>
</html>
