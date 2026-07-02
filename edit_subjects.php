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

$subCursor = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll]);
$subList   = iterator_to_array($subCursor);

// Check if CAT 1 is submitted
$cat1Submitted = false;
foreach ($subList as $sub) {
    if ($sub['internal'] === 'yes' && isset($sub['cat1'])) {
        $cat1Submitted = true;
        break;
    }
}

if ($cat1Submitted) { header("Location: semester_detail.php?id=$sem_id"); exit; }

$success = '';
if (isset($_POST['save'])) {
    foreach ($_POST['subject_name'] as $sub_id => $name) {
        $code     = $_POST['subject_code'][$sub_id];
        $credits  = (int)$_POST['credits'][$sub_id];
        $internal = $_POST['internal'][$sub_id];
        $subjects->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($sub_id)],
            ['$set' => ['subject_name' => $name, 'subject_code' => $code, 'credits' => $credits, 'internal' => $internal]]
        );
    }
    $success = "Subjects updated successfully.";
    $subCursor = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll]);
    $subList   = iterator_to_array($subCursor);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Edit Subjects</title>
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
                <div class="notif-dropdown-header">Notifications <a href="#" onclick="markAll(event)">Mark all read</a></div>
                <div id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
<div class="form-box">
    <h2>Edit Subjects – Semester <?= $sem['sem'] ?></h2>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <form method="POST">
        <div class="cat-table-wrap">
            <table class="cat-table">
                <thead>
                    <tr>
                        <th>Subject Name</th>
                        <th>Code</th>
                        <th>Credits</th>
                        <th>Internal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subList as $sub): $sid = (string)$sub['_id']; ?>
                    <tr>
                        <td><input type="text" name="subject_name[<?= $sid ?>]" value="<?= htmlspecialchars($sub['subject_name']) ?>" required></td>
                        <td><input type="text" name="subject_code[<?= $sid ?>]" value="<?= htmlspecialchars($sub['subject_code']) ?>" required></td>
                        <td><input type="number" name="credits[<?= $sid ?>]" value="<?= $sub['credits'] ?>" min="0" max="6" required style="width:70px;"></td>
                        <td>
                            <select name="internal[<?= $sid ?>]" required>
                                <option value="yes" <?= $sub['internal'] === 'yes' ? 'selected' : '' ?>>Yes</option>
                                <option value="no"  <?= $sub['internal'] === 'no'  ? 'selected' : '' ?>>No</option>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" name="save" class="btn-primary" style="margin-top:16px;">Save Changes</button>
    </form>
    <a href="semester_detail.php?id=<?= $sem_id ?>" class="btn-secondary" style="margin-top:12px;">Back to Semester</a>
</div>
</div>
<script>
function toggleNotif(){const d=document.getElementById('notifDrop');d.classList.toggle('open');if(d.classList.contains('open'))loadNotifs();}
function loadNotifs(){fetch('notifications.php?fetch=1').then(r=>r.json()).then(data=>{const l=document.getElementById('notifList');if(!data.length){l.innerHTML='<div class="notif-empty">No notifications</div>';return;}l.innerHTML=data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}</div><div class="notif-time">${n.time}</div></div>`).join('');});}
function markAll(e){e.preventDefault();fetch('notifications.php?mark_all=1');document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread'));const b=document.querySelector('.notif-badge');if(b)b.remove();}
document.addEventListener('click',e=>{const btn=document.getElementById('bellBtn');const d=document.getElementById('notifDrop');if(btn&&d&&!btn.contains(e.target)&&!d.contains(e.target))d.classList.remove('open');});
</script>
</body>
</html>


