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
        $mark   = (float)($_POST['cat'][$sub_id] ?? 0);
        $subjects->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($sub_id), 'roll' => $roll],
            ['$set' => ['cat3' => $mark]]
        );
    }
    // recalculate total and percentage per subject
    $subCursor2 = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll, 'internal' => 'yes']);
    foreach (iterator_to_array($subCursor2) as $sub) {
        $c1 = (float)($sub['cat1'] ?? 0);
        $c2 = (float)($sub['cat2'] ?? 0);
        $c3 = (float)($sub['cat3'] ?? 0);
        $total = $c1 + $c2 + $c3;
        $percentage = round(($total / 300) * 100, 2);
        $subjects->updateOne(
            ['_id' => $sub['_id']],
            ['$set' => ['total' => $total, 'percentage' => $percentage]]
        );
    }
    $success = "CAT 3 marks saved successfully.";
    $subCursor = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll, 'internal' => 'yes']);
    $subList   = iterator_to_array($subCursor);
}
$t = 0;
foreach ($subList as $sub) { if ((int)($sub['credits'] ?? 0) > 0) $t += ($sub['cat3'] ?? 0); }
$percent = $maxTotal > 0 ? round(($t / $maxTotal) * 100, 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – CAT 3 Marks</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="logo1.jpeg">
</head>
<body>
<nav class="navbar">
    <a href="dashboard.php" class="nav-brand"><img src="logo1.jpeg" alt="SGI">SGI</a>
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
    <h2>CAT 3 Marks – Semester <?= $sem['sem'] ?></h2>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
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
                <input type="number" name="cat[<?= $sid ?>]" min="0" max="100" step="0.01"
                    value="<?= $sub['cat3'] ?? '' ?>" oninput="calcTotal()" required>
                <div class="cat-grid-label">out of 100</div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="cat-exam-total" style="margin:16px 0;">
            <span>CAT 3 Total: <strong id="cat-total"><?= $t ?></strong> / <?= $maxTotal ?></span>
            <span>Percentage: <strong id="cat-percent"><?= $percent ?>%</strong></span>
        </div>
        <button type="submit" name="save_marks" class="btn-primary">Save CAT 3 Marks</button>
    </form>
    <?php endif; ?>
    <a href="semester_detail.php?id=<?= $sem_id ?>" class="btn-secondary" style="margin-top:12px;">Back to Semester</a>
</div>
</div>
<script>
const maxTotal = <?= $maxTotal ?>;
function calcTotal() {
    const items  = document.querySelectorAll('.cat-grid-item');
    let total = 0, max = 0;
    items.forEach(item => {
        const credits = parseInt(item.dataset.credits || '0');
        const val = parseFloat(item.querySelector('input').value) || 0;
        if (credits > 0) { total += val; max += 100; }
    });
    document.getElementById('cat-total').innerText   = total;
    document.getElementById('cat-percent').innerText = max > 0 ? ((total / max) * 100).toFixed(2) + '%' : '0%';
}
</script>
</body>
</html>


