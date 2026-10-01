<?php
include 'config.php';
include 'leap_auth.php';
$m = requireMentorLeap();

// Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $sess_id    = trim($_POST['session_id'] ?? '');
    $attendance = $_POST['attendance'] ?? [];   // ['roll' => 'PRESENT'|'ABSENT']

    $sess = $leap_training_sessions->findOne([
        '_id'       => new MongoDB\BSON\ObjectId($sess_id),
        'mentor_id' => $m['mentor_id'],
    ]);
    if ($sess && ($sess['status'] ?? '') === 'CONDUCTED') {
        foreach ($attendance as $roll => $status) {
            if (!in_array($status, ['PRESENT','ABSENT'])) continue;
            $leap_attendance->updateOne(
                ['session_id' => $sess_id, 'student_id' => $roll],
                ['$set' => [
                    'session_id' => $sess_id,
                    'student_id' => $roll,
                    'status'     => $status,
                    'marked_by'  => $m['mentor_id'],
                    'marked_at'  => new MongoDB\BSON\UTCDateTime(),
                ]],
                ['upsert' => true]
            );
            // Notify absent students
            if ($status === 'ABSENT') {
                leapNotifyStudent($roll,
                    "⚠️ You were marked ABSENT for LEAP training session \"{$sess['title']}\".",
                    'leap_training.php'
                );
            }
        }
        header('Location: mentor_leap_attendance.php?tr=' . $sess['training_id'] . '&sess=' . $sess_id . '&saved=1');
        exit;
    }
}

// Suspend session via attendance page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['suspend_session'])) {
    $sess_id = trim($_POST['session_id'] ?? '');
    $reason  = trim($_POST['suspension_reason'] ?? '');
    if (!$reason) {
        header('Location: mentor_leap_attendance.php?err=reason');
        exit;
    }
    $sess = $leap_training_sessions->findOne([
        '_id'       => new MongoDB\BSON\ObjectId($sess_id),
        'mentor_id' => $m['mentor_id'],
    ]);
    if ($sess) {
        $leap_training_sessions->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($sess_id)],
            ['$set' => ['status' => 'SUSPENDED', 'suspension_reason' => $reason]]
        );
        // Delete any existing attendance records for this session (shouldn't count)
        $leap_attendance->deleteMany(['session_id' => $sess_id]);
        // Notify students
        $mems = $leap_memberships->find(['mentor_id' => $m['mentor_id'], 'status' => 'ACTIVE']);
        foreach ($mems as $mem) {
            leapNotifyStudent($mem['student_id'],
                "⏸ LEAP training session \"{$sess['title']}\" has been suspended. Reason: {$reason}",
                'leap_training.php'
            );
        }
        header('Location: mentor_leap_attendance.php?tr=' . $sess['training_id'] . '&suspended=1');
        exit;
    }
}

// Load trainings
$trainings = iterator_to_array($leap_training->find(
    ['mentor_id' => $m['mentor_id']],
    ['sort' => ['created_at' => -1]]
));

$activeTrId   = $_GET['tr']   ?? (count($trainings) > 0 ? (string)$trainings[0]['_id'] : null);
$activeSessId = $_GET['sess'] ?? null;
$activeTr     = null;
$sessions     = [];
$activeSess   = null;
$students     = [];
$existingAtt  = [];

if ($activeTrId) {
    $activeTr = $leap_training->findOne(['_id' => new MongoDB\BSON\ObjectId($activeTrId), 'mentor_id' => $m['mentor_id']]);
    if ($activeTr) {
        $sessions = iterator_to_array($leap_training_sessions->find(
            ['training_id' => $activeTrId],
            ['sort' => ['session_number' => 1]]
        ));
    }
}

if ($activeSessId) {
    $activeSess = $leap_training_sessions->findOne([
        '_id'       => new MongoDB\BSON\ObjectId($activeSessId),
        'mentor_id' => $m['mentor_id'],
    ]);
    if ($activeSess) {
        // Get all active LEAP students for this mentor
        $mems = iterator_to_array($leap_memberships->find(['mentor_id' => $m['mentor_id'], 'status' => 'ACTIVE']));
        foreach ($mems as $mem) {
            $st = $users->findOne(['roll' => $mem['student_id']]);
            if ($st) $students[] = $st;
        }
        // Existing attendance records
        $attCursor = $leap_attendance->find(['session_id' => $activeSessId]);
        foreach ($attCursor as $att) {
            $existingAtt[$att['student_id']] = $att['status'];
        }
    }
}

$unreadCount = $notifications->countDocuments(['mentor_id' => $m['mentor_id'], 'read' => false]);
$statusColors = ['SCHEDULED' => '#17a2b8', 'CONDUCTED' => '#28a745', 'SUSPENDED' => '#f5a623', 'CANCELLED' => '#e94560'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Attendance (Mentor)</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .tr-tab{padding:9px 16px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;border:2px solid #e0e0e0;background:#fff;color:#555;transition:all .2s;display:block;margin-bottom:6px;text-decoration:none;}
        .tr-tab.active{background:linear-gradient(135deg,#1a1a2e,#17a2b8);color:#fff;border-color:transparent;}
        .sess-btn{padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;border:2px solid #e0e0e0;background:#fff;color:#555;cursor:pointer;transition:all .2s;text-decoration:none;display:inline-block;margin:4px;}
        .sess-btn.active{background:linear-gradient(135deg,#1a1a2e,#17a2b8);color:#fff;border-color:transparent;}
        .att-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #f0f2f5;}
        .att-row:last-child{border-bottom:none;}
        .att-toggle{display:flex;gap:8px;}
        .att-btn{padding:7px 18px;border:2px solid #e0e0e0;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;background:#fff;color:#555;transition:all .2s;}
        .att-btn.present.selected{background:#28a745;color:#fff;border-color:#28a745;}
        .att-btn.absent.selected{background:#e94560;color:#fff;border-color:#e94560;}
    </style>
</head>
<body>
<?php leapMentorNav($m, $unreadCount); ?>
<div class="container">
    <h2 style="color:#1a1a2e;margin-bottom:20px;">✅ LEAP Training Attendance</h2>

    <?php if (isset($_GET['saved'])): ?><p class="success" style="margin-bottom:12px;">✅ Attendance saved.</p><?php endif; ?>
    <?php if (isset($_GET['suspended'])): ?><p class="success" style="margin-bottom:12px;">⏸ Session suspended and students notified.</p><?php endif; ?>
    <?php if (isset($_GET['err']) && $_GET['err'] === 'reason'): ?><p class="error" style="margin-bottom:12px;">A suspension reason is required.</p><?php endif; ?>

    <div style="display:grid;grid-template-columns:220px 1fr;gap:24px;align-items:flex-start;">

        <!-- Training selector -->
        <div>
            <div style="font-size:12px;text-transform:uppercase;color:#888;letter-spacing:1px;margin-bottom:8px;">Training</div>
            <?php foreach ($trainings as $tr): ?>
            <a href="mentor_leap_attendance.php?tr=<?= (string)$tr['_id'] ?>"
               class="tr-tab <?= $activeTrId === (string)$tr['_id'] ? 'active' : '' ?>">
                <?= htmlspecialchars($tr['title']) ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Session + attendance -->
        <div>
            <?php if ($activeTr && !empty($sessions)): ?>
            <div style="margin-bottom:16px;">
                <div style="font-size:12px;text-transform:uppercase;color:#888;letter-spacing:1px;margin-bottom:8px;">Select Session</div>
                <?php foreach ($sessions as $sess):
                    $sc = $statusColors[$sess['status'] ?? 'SCHEDULED'] ?? '#6c757d';
                ?>
                <a href="mentor_leap_attendance.php?tr=<?= $activeTrId ?>&sess=<?= (string)$sess['_id'] ?>"
                   class="sess-btn <?= $activeSessId === (string)$sess['_id'] ? 'active' : '' ?>">
                    S<?= $sess['session_number'] ?>
                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $sc ?>;margin-left:4px;"></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($activeSess): ?>
            <?php $sessStatus = $activeSess['status'] ?? 'SCHEDULED'; ?>

            <div class="form-box" style="padding:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                    <div>
                        <h3 style="color:#1a1a2e;margin-bottom:4px;">Session <?= $activeSess['session_number'] ?>: <?= htmlspecialchars($activeSess['title']) ?></h3>
                        <?php if (!empty($activeSess['date'])): ?>
                        <div style="font-size:13px;color:#888;">📅 <?= htmlspecialchars($activeSess['date']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span style="background:<?= $statusColors[$sessStatus] ?? '#6c757d' ?>;color:#fff;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;"><?= $sessStatus ?></span>
                </div>

                <?php if ($sessStatus === 'SUSPENDED'): ?>
                <div style="background:#fff3cd;border:1px solid #f5a623;border-radius:10px;padding:14px;margin-bottom:16px;">
                    <strong style="color:#856404;">⏸ Session Suspended</strong>
                    <p style="color:#856404;font-size:13px;margin-top:4px;">Reason: <?= htmlspecialchars($activeSess['suspension_reason'] ?? '') ?></p>
                    <p style="color:#856404;font-size:12px;margin-top:4px;">This session is excluded from attendance calculations.</p>
                </div>

                <?php elseif ($sessStatus === 'CONDUCTED'): ?>
                <!-- Attendance form -->
                <?php if (empty($students)): ?>
                <p class="no-data">No active LEAP students found.</p>
                <?php else: ?>
                <form method="POST" id="attForm">
                    <input type="hidden" name="session_id" value="<?= $activeSessId ?>">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                        <span style="font-size:14px;font-weight:600;color:#1a1a2e;"><?= count($students) ?> students</span>
                        <button type="button" onclick="markAll('PRESENT')" style="padding:7px 16px;background:#28a745;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;">✅ Mark All Present</button>
                    </div>
                    <?php foreach ($students as $st):
                        $current = $existingAtt[$st['roll']] ?? 'PRESENT';
                    ?>
                    <div class="att-row">
                        <div style="min-width:0;">
                            <div style="font-weight:600;font-size:14px;"><a href="#" onclick="openLeapStudent('<?= htmlspecialchars($st['roll'], ENT_QUOTES) ?>');return false;" style="color:#8e44ad;text-decoration:none;" title="Click to view full details"><?= htmlspecialchars($st['name']) ?></a></div>
                            <div style="font-size:12px;color:#888;"><?= htmlspecialchars($st['roll']) ?> · <?= htmlspecialchars($st['dept']) ?></div>
                        </div>
                        <div class="att-toggle">
                            <button type="button"
                                class="att-btn present <?= $current === 'PRESENT' ? 'selected' : '' ?>"
                                onclick="setAtt('<?= $st['roll'] ?>','PRESENT',this)">Present</button>
                            <button type="button"
                                class="att-btn absent <?= $current === 'ABSENT' ? 'selected' : '' ?>"
                                onclick="setAtt('<?= $st['roll'] ?>','ABSENT',this)">Absent</button>
                            <input type="hidden" name="attendance[<?= htmlspecialchars($st['roll']) ?>]" id="att_<?= $st['roll'] ?>" value="<?= $current ?>">
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <button type="submit" name="save_attendance" class="btn-primary" style="margin-top:20px;">Save Attendance</button>
                </form>
                <?php endif; ?>

                <?php else: ?>
                <!-- Session not yet conducted — option to mark conducted or suspend -->
                <div style="background:#f8f9fa;border-radius:10px;padding:20px;text-align:center;">
                    <p style="color:#666;margin-bottom:16px;">This session has not been conducted yet. Mark it as conducted to record attendance, or suspend it.</p>
                    <form method="POST" style="display:inline-block;margin-right:10px;">
                        <input type="hidden" name="session_id" value="<?= $activeSessId ?>">
                        <input type="hidden" name="status" value="CONDUCTED">
                        <button type="submit" name="update_session" style="padding:10px 24px;background:#28a745;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">Mark as Conducted</button>
                    </form>
                    <button onclick="document.getElementById('suspendBox').style.display='block'" style="padding:10px 24px;background:#f5a623;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">⏸ Suspend Session</button>
                    <div id="suspendBox" style="display:none;margin-top:16px;text-align:left;">
                        <form method="POST">
                            <input type="hidden" name="session_id" value="<?= $activeSessId ?>">
                            <label style="font-size:13px;font-weight:600;">Suspension reason (required)</label>
                            <input type="text" name="suspension_reason" placeholder="e.g. Trainer unavailable" required style="margin-top:6px;">
                            <button type="submit" name="suspend_session" style="margin-top:10px;padding:10px 24px;background:#f5a623;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">Confirm Suspend</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php elseif ($activeTr): ?>
            <div style="background:#fff;border-radius:14px;padding:40px;text-align:center;color:#aaa;box-shadow:0 2px 10px rgba(0,0,0,0.07);">
                Select a session to mark attendance.
            </div>
            <?php else: ?>
            <div style="background:#fff;border-radius:14px;padding:40px;text-align:center;color:#aaa;box-shadow:0 2px 10px rgba(0,0,0,0.07);">
                Select a training program to get started.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php leapMentorNotifJS(); leapStudentDetailModal(); leapFooter(); ?>
<script>
function setAtt(roll, status, btn) {
    document.getElementById('att_' + roll).value = status;
    const row = btn.closest('.att-toggle');
    row.querySelectorAll('.att-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
}
function markAll(status) {
    document.querySelectorAll('.att-toggle').forEach(row => {
        const btn = row.querySelector('.att-btn.' + status.toLowerCase());
        if (btn) {
            const roll = btn.closest('.att-row').querySelector('input[type=hidden]').id.replace('att_','');
            setAtt(roll, status, btn);
        }
    });
}
</script>
</body>
</html>
