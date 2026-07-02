<?php
include 'config.php';
requireLogin();

if (empty($_GET['sem_id'])) { header("Location: dashboard.php"); exit; }

$sem_id = $_GET['sem_id'];
$roll   = $_SESSION['user']['roll'];
$u      = $_SESSION['user'];
$unreadCount = $notifications->countDocuments(['roll'=>$u['roll'],'read'=>false]);

$sem = $semesters->findOne(['_id' => new MongoDB\BSON\ObjectId($sem_id), 'roll' => $roll]);
if (!$sem) { header("Location: dashboard.php"); exit; }
if (empty($sem['verified'])) { header("Location: semester_detail.php?id=$sem_id"); exit; }

$subCursor = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll]);
$subList   = iterator_to_array($subCursor);

$success = '';
$uploadError = '';
if (isset($_POST['save_ca'])) {
    foreach ($subList as $sub) {
        $sub_id   = (string)$sub['_id'];
        $scored   = (float)($_POST['scored'][$sub_id] ?? 0);
        $max      = (float)($_POST['max'][$sub_id] ?? 0);
        $ca_percent = $max > 0 ? round(($scored / $max) * 100, 2) : 0;
        $subjects->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($sub_id)],
            ['$set' => ['ca_scored' => $scored, 'ca_max' => $max, 'ca_percent' => $ca_percent]]
        );
    }
    $gpa        = (float)$_POST['gpa'];
    $cgpa       = (float)$_POST['cgpa'];
    
    // Handle file uploads
    $updateData = ['gpa' => $gpa, 'cgpa' => $cgpa, 'ca_done' => true];
    
    if (!empty($_FILES['result_photo']['name'])) {
        if ($_FILES['result_photo']['size'] > 5 * 1024 * 1024) {
            $uploadError = "Semester result must be ≤ 5 MB.";
        } else {
            $result_photo = uploadToCloudinary($_FILES['result_photo']['tmp_name'], 'sgi/results', 'image');
            $updateData['result_photo'] = $result_photo;
        }
    }
    
    if (!$uploadError && !empty($_FILES['ca_photo']['name'])) {
        if ($_FILES['ca_photo']['size'] > 5 * 1024 * 1024) {
            $uploadError = "CA mark sheet must be ≤ 5 MB.";
        } else {
            $ca_photo = uploadToCloudinary($_FILES['ca_photo']['tmp_name'], 'sgi/ca_marks');
            $updateData['ca_photo'] = $ca_photo;
        }
    }
    
    if (!$uploadError) {
        $semesters->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($sem_id)],
            ['$set' => $updateData]
        );
        $success = "Final CA marks, academic details, and documents saved successfully.";
        $subCursor = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll]);
        $subList   = iterator_to_array($subCursor);
        $sem = $semesters->findOne(['_id' => new MongoDB\BSON\ObjectId($sem_id), 'roll' => $roll]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Final CA Marks</title>
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
    <h2>Final CA Marks – Semester <?= $sem['sem'] ?></h2>
    <hr style="margin:16px 0;">
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <?php if ($uploadError): ?><p class="error"><?= $uploadError ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <h3>Academic Details</h3>
        <div class="detail-grid" style="margin-bottom:20px;">
            <div class="detail-item">
                <label>GPA (out of 10)</label>
                <input type="number" name="gpa" step="0.01" min="0" max="10" value="<?= $sem['gpa'] ?? '' ?>" required>
            </div>
            <div class="detail-item">
                <label>CGPA (out of 10)</label>
                <input type="number" name="cgpa" step="0.01" min="0" max="10" value="<?= $sem['cgpa'] ?? '' ?>" required>
            </div>
        </div>
        <hr style="margin:16px 0;">

        <h3>Final CA Marks</h3>
        <div class="cat-table-wrap">
            <table class="cat-table">
                <thead>
                    <tr>
                        <th>Subject Name</th>
                        <th>Code</th>
                        <th>Credits</th>
                        <th>Internal</th>
                        <th>Marks Scored</th>
                        <th>Max Marks</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subList as $sub): $sid = (string)$sub['_id']; ?>
                    <tr>
                        <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                        <td><?= htmlspecialchars($sub['subject_code']) ?></td>
                        <td><?= $sub['credits'] ?></td>
                        <td><?= ucfirst($sub['internal']) ?></td>
                        <td>
                            <input type="number" name="scored[<?= $sid ?>]" min="0" step="0.01"
                                value="<?= $sub['ca_scored'] ?? '' ?>"
                                oninput="calcPercent('<?= $sid ?>')" style="width:90px;">
                        </td>
                        <td>
                            <input type="number" name="max[<?= $sid ?>]" min="0" step="0.01"
                                value="<?= $sub['ca_max'] ?? '' ?>"
                                oninput="calcPercent('<?= $sid ?>')" style="width:90px;">
                        </td>
                        <td>
                            <input type="text" id="percent-<?= $sid ?>" readonly
                                value="<?= !empty($sub['ca_percent']) ? $sub['ca_percent'].'%' : '' ?>"
                                style="width:80px;background:#f5f5f5;color:#888;">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <hr style="margin:20px 0;">

        <h3>Upload Documents</h3>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label>Semester Result (PDF/Image, ≤ 5 MB)</label>
                <input type="file" name="result_photo" accept=".pdf,image/*" style="margin-top:6px;">
                <?php if (!empty($sem['result_photo'])): ?>
                    <p style="font-size:12px;color:#27ae60;margin-top:4px;">✓ Document already uploaded</p>
                <?php endif; ?>
            </div>
            <div>
                <label>CA Mark Sheet (PDF/Image, ≤ 5 MB)</label>
                <input type="file" name="ca_photo" accept=".pdf,image/*" style="margin-top:6px;">
                <?php if (!empty($sem['ca_photo'])): ?>
                    <p style="font-size:12px;color:#27ae60;margin-top:4px;">✓ Document already uploaded</p>
                <?php endif; ?>
            </div>
        </div>

        <button type="submit" name="save_ca" class="btn-primary" style="margin-top:20px;">Save CA Marks, GPA/CGPA & Documents</button>
    </form>

    <a href="semester_detail.php?id=<?= $sem_id ?>" class="btn-secondary" style="margin-top:12px;">Back to Semester</a>
</div>
</div>
<script>
function calcPercent(sid) {
    const scored = parseFloat(document.querySelector(`input[name="scored[${sid}]"]`).value) || 0;
    const max    = parseFloat(document.querySelector(`input[name="max[${sid}]"]`).value) || 0;
    document.getElementById(`percent-${sid}`).value = max > 0 ? ((scored / max) * 100).toFixed(2) + '%' : '';
}
function toggleNotif(){const d=document.getElementById('notifDrop');d.classList.toggle('open');if(d.classList.contains('open'))loadNotifs();}
function loadNotifs(){fetch('notifications.php?fetch=1').then(r=>r.json()).then(data=>{const l=document.getElementById('notifList');if(!data.length){l.innerHTML='<div class="notif-empty">No notifications</div>';return;}l.innerHTML=data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}</div><div class="notif-time">${n.time}</div></div>`).join('');});}
function markAll(e){e.preventDefault();fetch('notifications.php?mark_all=1');document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread'));const b=document.querySelector('.notif-badge');if(b)b.remove();}
document.addEventListener('click',e=>{const btn=document.getElementById('bellBtn');const d=document.getElementById('notifDrop');if(btn&&d&&!btn.contains(e.target)&&!d.contains(e.target))d.classList.remove('open');});
</script>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>


