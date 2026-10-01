<?php
include 'config.php';
include 'leap_auth.php';
$m = requireMentorLeap();

// ── Accept application ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept'])) {
    $app_id = trim($_POST['app_id'] ?? '');
    $app = $leap_applications->findOne([
        '_id'       => new MongoDB\BSON\ObjectId($app_id),
        'mentor_id' => $m['mentor_id'],
        'status'    => 'PENDING',
    ]);
    if ($app) {
        $leap_applications->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($app_id)],
            ['$set' => ['status' => 'ACCEPTED', 'reviewed_at' => new MongoDB\BSON\UTCDateTime()]]
        );
        // Create membership
        $existing = $leap_memberships->findOne(['student_id' => $app['student_id']]);
        if (!$existing) {
            $leap_memberships->insertOne([
                'student_id'    => $app['student_id'],
                'application_id'=> $app_id,
                'mentor_id'     => $m['mentor_id'],
                'pacc_level'    => null,
                'pacc_assigned_by' => null,
                'pacc_assigned_at' => null,
                'joined_at'     => new MongoDB\BSON\UTCDateTime(),
                'status'        => 'ACTIVE',
            ]);
        } else {
            $leap_memberships->updateOne(
                ['student_id' => $app['student_id']],
                ['$set' => ['status' => 'ACTIVE', 'mentor_id' => $m['mentor_id'], 'application_id' => $app_id]]
            );
        }
        leapNotifyStudent($app['student_id'],
            "🎉 Your LEAP application has been ACCEPTED by {$m['name']}! Welcome to LEAP.",
            'leap.php'
        );
    }
    header('Location: mentor_leap_applications.php?accepted=1');
    exit;
}

// ── Reject application ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject'])) {
    $app_id = trim($_POST['app_id'] ?? '');
    $reason = trim($_POST['rejection_reason'] ?? '');
    $app = $leap_applications->findOne([
        '_id'       => new MongoDB\BSON\ObjectId($app_id),
        'mentor_id' => $m['mentor_id'],
        'status'    => 'PENDING',
    ]);
    if ($app) {
        $leap_applications->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($app_id)],
            ['$set' => [
                'status'           => 'REJECTED',
                'rejection_reason' => $reason,
                'reviewed_at'      => new MongoDB\BSON\UTCDateTime(),
            ]]
        );
        leapNotifyStudent($app['student_id'],
            "❌ Your LEAP application was rejected by {$m['name']}." . ($reason ? " Reason: $reason" : '') . " You may apply again.",
            'leap_apply.php'
        );
    }
    header('Location: mentor_leap_applications.php?rejected=1');
    exit;
}

// Fetch all applications for this mentor (newest first)
$apps = iterator_to_array($leap_applications->find(
    ['mentor_id' => $m['mentor_id']],
    ['sort' => ['submitted_at' => -1]]
));

$unreadCount = $notifications->countDocuments(['mentor_id' => $m['mentor_id'], 'read' => false]);
$pending = array_filter($apps, fn($a) => ($a['status'] ?? '') === 'PENDING');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Applications</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .app-card{background:#fff;border-radius:12px;padding:20px 24px;margin-bottom:16px;box-shadow:0 2px 10px rgba(0,0,0,0.07);border-left:4px solid #ccc;}
        .app-card.pending{border-left-color:#f5a623;}
        .app-card.accepted{border-left-color:#28a745;}
        .app-card.rejected{border-left-color:#e94560;}
        .status-badge{display:inline-block;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700;color:#fff;}
        .badge-pending{background:#f5a623;}
        .badge-accepted{background:#28a745;}
        .badge-rejected{background:#e94560;}
        .badge-not_willing{background:#6c757d;}
    </style>
</head>
<body>
<?php leapMentorNav($m, $unreadCount); ?>
<div class="container">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <div>
            <h2 style="color:#1a1a2e;margin-bottom:4px;">LEAP Applications</h2>
            <p style="color:#888;font-size:13px;"><?= count($apps) ?> total · <?= count($pending) ?> pending</p>
        </div>
    </div>

    <?php if (isset($_GET['accepted'])): ?><p class="success" style="margin-bottom:16px;">✅ Application accepted. Student now has LEAP access.</p><?php endif; ?>
    <?php if (isset($_GET['rejected'])): ?><p class="error" style="margin-bottom:16px;">Application rejected and student notified.</p><?php endif; ?>

    <?php if (empty($apps)): ?>
        <p class="no-data">No LEAP applications yet.</p>
    <?php else: ?>
        <?php foreach ($apps as $app):
            $st = $app['status'] ?? 'PENDING';
            $badgeClass = 'badge-' . strtolower($st);
            $cardClass  = strtolower($st);
        ?>
        <div class="app-card <?= $cardClass ?>">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
                <div style="min-width:0;">
                    <div style="font-size:17px;font-weight:700;color:#1a1a2e;overflow-wrap:anywhere;"><a href="#" onclick="openLeapStudent('<?= htmlspecialchars($app['student_id'], ENT_QUOTES) ?>');return false;" style="color:#8e44ad;text-decoration:none;" title="Click to view attendance, results, tests"><?= htmlspecialchars($app['name']) ?></a></div>
                    <div style="font-size:13px;color:#666;margin-top:2px;">
                        <?= htmlspecialchars($app['roll_no']) ?> &nbsp;·&nbsp;
                        <?= htmlspecialchars($app['integrated_no']) ?> &nbsp;·&nbsp;
                        <?= htmlspecialchars($app['department']) ?>
                    </div>
                    <div style="font-size:12px;color:#888;margin-top:2px;">
                        Batch: <?= htmlspecialchars($app['batch_no']) ?> &nbsp;·&nbsp;
                        <?= htmlspecialchars($app['gmail']) ?>
                    </div>
                    <div style="margin-top:8px;">
                        <span class="status-badge <?= $badgeClass ?>"><?= $st ?></span>
                        <span style="font-size:12px;color:#aaa;margin-left:10px;">
                            Submitted: <?= date('d M Y, h:i A', $app['submitted_at']->toDateTime()->getTimestamp()) ?>
                        </span>
                        <?php if (!empty($app['reviewed_at'])): ?>
                        <span style="font-size:12px;color:#aaa;margin-left:10px;">
                            Reviewed: <?= date('d M Y', $app['reviewed_at']->toDateTime()->getTimestamp()) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($st === 'REJECTED' && !empty($app['rejection_reason'])): ?>
                    <div style="font-size:12px;color:#e94560;margin-top:4px;">Reason: <?= htmlspecialchars($app['rejection_reason']) ?></div>
                    <?php endif; ?>
                </div>

                <?php if ($st === 'PENDING'): ?>
                <div style="display:flex;gap:10px;align-items:flex-start;">
                    <!-- Accept -->
                    <form method="POST" onsubmit="return sgiConfirmFormSubmit(this,'Accept this LEAP application? Student will be notified and added to LEAP.','accept','Accept Application','Yes, Accept')">
                        <input type="hidden" name="app_id" value="<?= (string)$app['_id'] ?>">
                        <button type="submit" style="padding:9px 20px;background:#28a745;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">✅ Accept</button>
                    </form>
                    <!-- Reject -->
                    <button onclick="sgiConfirm('Reject this LEAP application? You can add a reason in the next step.','Reject Application','Reject').then(ok=>{if(ok)showRejectForm('<?= (string)$app['_id'] ?>')})" style="padding:9px 20px;background:#e94560;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">❌ Reject</button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Reject form (webpage modal) -->
            <?php if ($st === 'PENDING'): ?>
            <div id="rejectForm_<?= (string)$app['_id'] ?>" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;backdrop-filter:blur(4px);" onclick="if(event.target===this)hideRejectForm('<?= (string)$app['_id'] ?>')">
                <div style="background:#fff;border-radius:20px;padding:28px;max-width:440px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                    <div style="text-align:center;">
                        <div style="font-size:48px;margin-bottom:12px;">❌</div>
                        <h3 style="margin-bottom:8px;color:#1a1a2e;font-size:18px;">Reject Application</h3>
                        <p style="color:#666;margin-bottom:16px;font-size:14px;line-height:1.5;">
                            Reject <strong><?= htmlspecialchars($app['name']) ?> (<?= htmlspecialchars($app['student_id']) ?>)</strong>?
                            Student will be notified and can apply again.
                        </p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="app_id" value="<?= (string)$app['_id'] ?>">
                        <label style="font-size:13px;color:#555;font-weight:600;">Rejection reason (optional)</label>
                        <textarea name="rejection_reason" rows="2" placeholder="Enter reason… (shown to student)" style="margin-top:6px;"></textarea>
                        <div style="display:flex;gap:10px;margin-top:14px;">
                            <button type="button" onclick="hideRejectForm('<?= (string)$app['_id'] ?>')" style="flex:1;padding:12px;background:#e9ecef;color:#555;border:none;border-radius:10px;cursor:pointer;font-weight:600;">Cancel</button>
                            <button type="submit" name="reject" style="flex:1;padding:12px;background:#e94560;color:#fff;border:none;border-radius:10px;cursor:pointer;font-weight:600;">Confirm Reject</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>
<?php leapMentorNotifJS(); leapStudentDetailModal(); leapFooter(); ?>
<script>
function showRejectForm(id) { const el=document.getElementById('rejectForm_' + id); el.style.display='flex'; document.body.style.overflow='hidden'; }
function hideRejectForm(id) { document.getElementById('rejectForm_' + id).style.display='none'; document.body.style.overflow=''; }
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('[id^="rejectForm_"]').forEach(el=>{ if(el.style.display==='flex'){ el.style.display='none'; document.body.style.overflow=''; } }); });
</script>
</body>
</html>
