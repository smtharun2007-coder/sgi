/*<?php
include 'config.php';
requireLogin();

if (empty($_GET['sem_id'])) { header("Location: dashboard.php"); exit; }

$sem_id = $_GET['sem_id'];
$roll   = $_SESSION['user']['roll'];
$u      = $_SESSION['user'];
$unreadCount = $notifications->countDocuments(['roll'=>$u['roll'],'read'=>false]);

$sem = $semesters->findOne(['_id' => new MongoDB\BSON\ObjectId($sem_id), 'roll' => $roll]);
if (!$sem) { header("Location: dashboard.php"); exit; }

$subCursor = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll, 'internal' => 'yes']);
$subList   = iterator_to_array($subCursor);
$maxTotal  = count(array_filter($subList, fn($s) => (int)($s['credits'] ?? 0) > 0)) * 100;

$cat1Total = 0; $cat2Total = 0; $cat3Total = 0;
foreach ($subList as $sub) {
    if ((int)($sub['credits'] ?? 0) === 0) continue;
    $cat1Total += (float)($sub['cat1'] ?? 0);
    $cat2Total += (float)($sub['cat2'] ?? 0);
    $cat3Total += (float)($sub['cat3'] ?? 0);
}

if (isset($_POST['verify'])) {
    $prev_gpa   = (float)$_POST['prev_gpa'];
    $attendance = (float)$_POST['attendance'];
    
    // Check if there's already a pending approval for this
    $existingApproval = $approvals->findOne([
        'student_roll' => $roll,
        'semester' => (int)$sem['sem'],
        'type' => 'Verification',
        'status' => 'pending'
    ]);
    
    if ($existingApproval) {
        $error_verify = "You already have a pending Verification approval for this semester.";
    } else {
        // Prepare CAT marks data for all subjects
        $catMarksData = [];
        foreach ($subList as $sub) {
            $catMarksData[] = [
                'subject_id' => (string)$sub['_id'],
                'subject_name' => $sub['subject_name'],
                'subject_code' => $sub['subject_code'],
                'cat1' => ($sub['cat1'] ?? null) === 'nil' ? 'nil' : ($sub['cat1'] ?? ''),
                'cat2' => ($sub['cat2'] ?? null) === 'nil' ? 'nil' : ($sub['cat2'] ?? ''),
                'cat3' => ($sub['cat3'] ?? null) === 'nil' ? 'nil' : ($sub['cat3'] ?? ''),
                'total' => $sub['total'] ?? '',
                'percentage' => $sub['percentage'] ?? ''
            ];
        }
        
        // Create approval request
        $approvalData = [
            'student_roll' => $roll,
            'student_name' => $u['name'],
            'type' => 'Verification',
            'semester' => (int)$sem['sem'],
            'reg' => $u['reg'],
            'mentor_id' => $u['mentor_id'] ?? '',
            'message' => 'Request to verify marks for Semester ' . $sem['sem'],
            'status' => 'pending',
            'verification_data' => [
                'prev_gpa' => $prev_gpa,
                'attendance' => $attendance
            ],
            'cat_marks' => $catMarksData,
            'subject_count' => count($subList),
            'sem_id' => $sem_id,
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ];
        
        $approvals->insertOne($approvalData);
        
        // Create notification for mentor
        if (!empty($u['mentor_id'])) {
            $notifications->insertOne([
                'mentor_id' => $u['mentor_id'],
                'message' => '📝 Verification approval request from ' . $u['name'] . ' (' . $roll . ') - Semester ' . $sem['sem'],
                'read' => false,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);
        }
        
        // Create notification for student
        $notifications->insertOne([
            'roll' => $roll,
            'message' => '✅ Your Verification request for Semester ' . $sem['sem'] . ' has been submitted and is pending mentor approval.',
            'read' => false,
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        
        $success = 'Your Verification request has been submitted for approval. You will be notified once your mentor reviews it.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Verify & Confirm</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<nav class="navbar">
<a href="dashboard.php" class="nav-brand">
    <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo">
    SGI
</a>
    <div class="nav-links">
        <a href="semester_detail.php?id=<?= $sem_id ?>">&#8592; Back</a>
        <a href="update_profile.php">Profile</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="print_select.php">Print</a>
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
                &#128276;<?php if($unreadCount>0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <span style="display:flex;gap:10px;"><a href="#" onclick="markAll(event)">Mark read</a><a href="#" onclick="clearAll(event)">Clear all</a></span></div>
                <div class="notif-list-scroll" id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
<div class="form-box">
    <h2>Verify & Confirm – Semester <?= $sem['sem'] ?></h2>
    <hr style="margin:16px 0;">
    <?php if (isset($success)): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <?php if (isset($error_verify)): ?><p class="error"><?= $error_verify ?></p><?php endif; ?>
    <h3>CAT Marks Summary</h3>
    <div class="cat-table-wrap">
        <table class="cat-table">
            <thead>
                <tr><th>Subject</th><th>Code</th><th>CAT 1</th><th>CAT 2</th><th>CAT 3</th><th>Total</th><th>%</th></tr>
            </thead>
            <tbody>
                <?php foreach ($subList as $sub): ?>
                <tr>
                    <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                    <td><?= htmlspecialchars($sub['subject_code']) ?></td>
                    <td><?= $sub['cat1'] ?? '—' ?></td>
                    <td><?= $sub['cat2'] ?? '—' ?></td>
                    <td><?= $sub['cat3'] ?? '—' ?></td>
                    <td><?= $sub['total'] ?? '—' ?></td>
                    <td><?= !empty($sub['percentage']) ? $sub['percentage'].'%' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#1a1a2e;color:#fff;font-weight:700;">
                    <td colspan="2">CAT Total</td>
                    <td><?= $cat1Total ?> / <?= $maxTotal ?><br><small><?= $maxTotal > 0 ? round(($cat1Total/$maxTotal)*100,2) : 0 ?>%</small></td>
                    <td><?= $cat2Total ?> / <?= $maxTotal ?><br><small><?= $maxTotal > 0 ? round(($cat2Total/$maxTotal)*100,2) : 0 ?>%</small></td>
                    <td><?= $cat3Total ?> / <?= $maxTotal ?><br><small><?= $maxTotal > 0 ? round(($cat3Total/$maxTotal)*100,2) : 0 ?>%</small></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <hr style="margin:24px 0;">
    <h3>Academic Details</h3>
    <form method="POST">
        <label>Previous Semester GPA (out of 10)</label>
        <input type="number" name="prev_gpa" step="0.01" min="0" max="10" value="<?= $sem['prev_gpa'] ?? '' ?>" required>
        <label>Attendance %</label>
        <input type="number" name="attendance" step="0.01" min="0" max="100" value="<?= $sem['attendance'] ?? '' ?>" required>
        <div class="declaration-box">
            <label class="declaration-label">
                <input type="checkbox" id="declaration" onchange="toggleSubmit()" required>
                I hereby declare that the above CAT marks, Previous GPA, and Attendance information provided by me are true and correct to the best of my knowledge.
            </label>
        </div>
        <button type="submit" name="verify" id="submitBtn" class="btn-primary" style="margin-top:20px;" disabled>Confirm & Save</button>
    </form>
    <a href="semester_detail.php?id=<?= $sem_id ?>" class="btn-secondary" style="margin-top:12px;">Back to Semester</a>
</div>
</div>
<script>
function toggleSubmit() {
    document.getElementById('submitBtn').disabled = !document.getElementById('declaration').checked;
}
function toggleNotif() {
    const drop = document.getElementById('notifDrop');
    drop.classList.toggle('open');
    if (drop.classList.contains('open')) loadNotifs();
}
function loadNotifs() {
    fetch('notifications.php?fetch=1')
        .then(r=>r.json()).then(data=>{
            const list = document.getElementById('notifList');
            if (!data.length) { list.innerHTML='<div class="notif-empty">No notifications</div>'; return; }
            list.innerHTML = data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}</div><div class="notif-time">${n.time}</div></div>`).join('');
        });
}
function markAll(e) {
    e.preventDefault();
    fetch('notifications.php?mark_all=1');
    document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread'));
    const badge = document.querySelector('.notif-badge');
    if(badge) badge.remove();
}
function clearAll(e) {
    e.preventDefault();
    fetch('notifications.php?delete_all=1');
    document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>';
    const badge = document.querySelector('.notif-badge');
    if(badge) badge.remove();
}
document.addEventListener('click', e => {
    const btn = document.getElementById('bellBtn');
    const drop = document.getElementById('notifDrop');
    if (btn && drop && !btn.contains(e.target) && !drop.contains(e.target))
        drop.classList.remove('open');
});
</script>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>