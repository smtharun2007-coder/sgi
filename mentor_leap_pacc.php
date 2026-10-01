<?php
include 'config.php';
include 'leap_auth.php';
$m = requireMentorLeap();

// Assign / update PACC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_pacc'])) {
    $student_id = trim($_POST['student_id'] ?? '');
    $level      = trim($_POST['pacc_level'] ?? '');
    if ($student_id && in_array($level, ['ELITE', 'SUPER', 'ADVANCE'])) {
        // Verify this student belongs to this mentor's LEAP
        $mem = $leap_memberships->findOne(['student_id' => $student_id, 'mentor_id' => $m['mentor_id'], 'status' => 'ACTIVE']);
        if ($mem) {
            $leap_memberships->updateOne(
                ['student_id' => $student_id, 'mentor_id' => $m['mentor_id']],
                ['$set' => [
                    'pacc_level'       => $level,
                    'pacc_assigned_by' => $m['mentor_id'],
                    'pacc_assigned_at' => new MongoDB\BSON\UTCDateTime(),
                ]]
            );
            leapNotifyStudent($student_id,
                "⭐ Your PACC level has been assigned as {$level} by {$m['name']}.",
                'leap.php'
            );
        }
    }
    header('Location: mentor_leap_pacc.php?saved=1');
    exit;
}

// Fetch all active LEAP students under this mentor
$memberships = iterator_to_array($leap_memberships->find([
    'mentor_id' => $m['mentor_id'],
    'status'    => 'ACTIVE',
]));

$students = [];
foreach ($memberships as $mem) {
    $st = $users->findOne(['roll' => $mem['student_id']]);
    if ($st) {
        $students[] = [
            'student'    => $st,
            'membership' => $mem,
        ];
    }
}

$unreadCount = $notifications->countDocuments(['mentor_id' => $m['mentor_id'], 'read' => false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – PACC Allocation</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .pacc-table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.07);}
        .pacc-table th{background:linear-gradient(135deg,#1a1a2e,#f5a623);color:#fff;padding:12px 16px;text-align:left;font-size:13px;}
        .pacc-table td{padding:12px 16px;border-bottom:1px solid #f0f2f5;font-size:14px;color:#444;}
        .pacc-table tr:last-child td{border-bottom:none;}
        .pacc-table tr:hover td{background:#fafafa;}
        .pacc-select{padding:8px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;outline:none;cursor:pointer;}
        .pacc-select:focus{border-color:#f5a623;}
        .badge-elite{background:#f5a623;color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;}
        .badge-super{background:#8e44ad;color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;}
        .badge-advance{background:#17a2b8;color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;}
        .badge-none{background:#e0e0e0;color:#888;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;}
    </style>
</head>
<body>
<?php leapMentorNav($m, $unreadCount); ?>
<div class="container">

    <h2 style="color:#1a1a2e;margin-bottom:6px;">PACC Allocation</h2>
    <p style="color:#888;font-size:13px;margin-bottom:20px;">Assign or update PACC levels for your LEAP students. Students cannot modify their own PACC.</p>

    <?php if (isset($_GET['saved'])): ?><p class="success" style="margin-bottom:16px;">✅ PACC updated and student notified.</p><?php endif; ?>

    <?php if (empty($students)): ?>
        <p class="no-data">No active LEAP students yet.</p>
    <?php else: ?>
    <table class="pacc-table">
        <thead>
            <tr>
                <th>Student</th>
                <th>Roll No</th>
                <th>Batch</th>
                <th>Current PACC</th>
                <th>Coding Profiles</th>
                <th>Assign / Edit</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $row):
            $st  = $row['student'];
            $mem = $row['membership'];
            $currentPacc = $mem['pacc_level'] ?? null;
            $badgeClass  = $currentPacc ? 'badge-' . strtolower($currentPacc) : 'badge-none';
        ?>
        <tr>
            <td><strong><?= htmlspecialchars($st['name']) ?></strong><br><span style="font-size:12px;color:#888;"><?= htmlspecialchars($st['dept']) ?></span></td>
            <td><?= htmlspecialchars($st['roll']) ?></td>
            <td><?= htmlspecialchars($st['batch_no'] ?? '—') ?></td>
            <td><span class="<?= $badgeClass ?>"><?= $currentPacc ?? 'Not Assigned' ?></span></td>
            <td>
                <?php
                $appRec = $leap_applications->findOne(['_id' => new MongoDB\BSON\ObjectId($mem['application_id'])]);
                $lc = $appRec['leetcode_profile']   ?? '';
                $hr = $appRec['hackerrank_profile']  ?? '';
                ?>
                <?php if ($lc): ?>
                <a href="<?= htmlspecialchars($lc) ?>" target="_blank" rel="noopener noreferrer"
                   style="display:inline-block;margin-bottom:4px;padding:5px 12px;background:#f5a623;color:#fff;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;">LeetCode ↗</a><br>
                <?php else: ?>
                <span style="font-size:12px;color:#ccc;">LeetCode: —</span><br>
                <?php endif; ?>
                <?php if ($hr): ?>
                <a href="<?= htmlspecialchars($hr) ?>" target="_blank" rel="noopener noreferrer"
                   style="display:inline-block;margin-top:2px;padding:5px 12px;background:#2ec866;color:#fff;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;">HackerRank ↗</a>
                <?php else: ?>
                <span style="font-size:12px;color:#ccc;">HackerRank: —</span>
                <?php endif; ?>
            </td>
            <td>
                <form method="POST" style="display:flex;gap:8px;align-items:center;">
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($st['roll']) ?>">
                    <select name="pacc_level" class="pacc-select">
                        <option value="">— Select —</option>
                        <option value="ELITE"   <?= $currentPacc === 'ELITE'   ? 'selected' : '' ?>>Elite</option>
                        <option value="SUPER"   <?= $currentPacc === 'SUPER'   ? 'selected' : '' ?>>Super</option>
                        <option value="ADVANCE" <?= $currentPacc === 'ADVANCE' ? 'selected' : '' ?>>Advance</option>
                    </select>
                    <button type="submit" name="assign_pacc" style="padding:8px 16px;background:#1a1a2e;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;">Save</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

</div>
<?php leapMentorNotifJS(); leapFooter(); ?>
</body>
</html>
