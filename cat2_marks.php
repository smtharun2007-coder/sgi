<?php
include 'config.php';
requireLogin();

if (empty($_GET['sem_id'])) { header("Location: dashboard.php"); exit; }
$sem_id = $_GET['sem_id'];
$roll   = $_SESSION['user']['roll'];

$sem = $semesters->findOne(['_id' => new MongoDB\BSON\ObjectId($sem_id), 'roll' => $roll]);
if (!$sem) { header("Location: dashboard.php"); exit; }

$subCursor = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll, 'internal' => 'yes']);
$subList   = iterator_to_array($subCursor);
$maxTotal  = count(array_filter($subList, fn($s) => (int)($s['credits'] ?? 0) > 0)) * 100;

$success = '';
if (isset($_POST['save_marks'])) {
    foreach ($subList as $sub) {
        $sub_id = (string)$sub['_id'];
        $raw    = strtoupper(trim($_POST['cat'][$sub_id] ?? ''));
        if ($raw === 'NIL') {
            $subjects->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($sub_id), 'roll' => $roll],
                ['$set' => ['cat2' => 'nil']]
            );
        } else {
            $mark = ($raw === 'AB') ? 0 : (float)$raw;
            $subjects->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($sub_id), 'roll' => $roll],
                ['$set' => ['cat2' => $mark]]
            );
        }
    }
    $success = "CAT 2 marks saved successfully.";
    $subCursor = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll, 'internal' => 'yes']);
    $subList   = iterator_to_array($subCursor);
}
$t = 0; $maxTotal = 0;
foreach ($subList as $sub) {
    if ((int)($sub['credits'] ?? 0) === 0) continue;
    if (($sub['cat2'] ?? null) === 'nil') continue;
    $maxTotal += 100;
    $t += (float)($sub['cat2'] ?? 0);
}
$percent = $maxTotal > 0 ? round(($t / $maxTotal) * 100, 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – CAT 2 Marks</title>
    <link rel="stylesheet" href="/css/style.css?v=2">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<nav class="navbar">
    <span class="nav-brand">SGI</span>
    <div class="nav-links">
        <a href="dashboard.php">Home</a>
        <a href="update_profile.php">Update Profile</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="print_select.php">Print</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
<div class="form-box">
    <h2>CAT 2 Marks – Semester <?= $sem['sem'] ?></h2>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <div style="display:flex;gap:12px;margin-bottom:16px;">
        <span style="background:#f5a623;color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;">AB = Absent for Exam</span>
        <span style="background:#aaa;color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;">NIL = Exam not conducted</span>
    </div>
    <?php if (empty($subList)): ?>
        <p class="no-data">No internal subjects found for this semester.</p>
    <?php else: ?>
    <form method="POST">
        <div class="cat-grid">
            <?php foreach ($subList as $sub): ?>
            <?php $sid = (string)$sub['_id']; ?>
            <div class="cat-grid-item" data-credits="<?= (int)($sub['credits'] ?? 0) ?>">
                <div class="cat-grid-name"><?= htmlspecialchars($sub['subject_name']) ?></div>
                <div class="cat-grid-code"><?= htmlspecialchars($sub['subject_code']) ?></div>
                <input type="text" name="cat[<?= $sid ?>]" placeholder="0 / AB / NIL"
                    value="<?= isset($sub['cat2']) ? (($sub['cat2'] === 'nil') ? 'NIL' : $sub['cat2']) : '' ?>" oninput="calcTotal()">
                <div class="cat-grid-label">out of 100</div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="cat-exam-total" style="margin:16px 0;">
            <span>CAT 2 Total: <strong id="cat-total"><?= $t ?></strong> / <?= $maxTotal ?></span>
            <span>Percentage: <strong id="cat-percent"><?= $percent ?>%</strong></span>
        </div>
        <button type="submit" name="save_marks" class="btn-primary">Save CAT 2 Marks</button>
    </form>
    <?php endif; ?>
    <a href="semester_detail.php?id=<?= $sem_id ?>" class="btn-secondary" style="margin-top:12px;">Back to Semester</a>
</div>
</div>
<script>
function calcTotal() {
    const items = document.querySelectorAll('.cat-grid-item');
    let total = 0, max = 0;
    items.forEach(item => {
        const credits = parseInt(item.dataset.credits || '0');
        if (credits === 0) return;
        const val = item.querySelector('input').value.trim().toUpperCase();
        if (val === 'NIL') return;
        max += 100;
        total += (val === 'AB') ? 0 : (parseFloat(val) || 0);
    });
    document.getElementById('cat-total').innerText   = total;
    document.getElementById('cat-percent').innerText = max > 0 ? ((total / max) * 100).toFixed(2) + '%' : '0%';
}
</script>
</body>
</html>


