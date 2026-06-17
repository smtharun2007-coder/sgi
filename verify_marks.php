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

$cat1Total = 0; $cat2Total = 0; $cat3Total = 0;
foreach ($subList as $sub) {
    if ((int)($sub['credits'] ?? 0) === 0) continue;
    $cat1Total += (float)($sub['cat1'] ?? 0);
    $cat2Total += (float)($sub['cat2'] ?? 0);
    $cat3Total += (float)($sub['cat3'] ?? 0);
}

if (isset($_POST['verify'])) {
    $prev_gpa   = (float)$_POST['prev_gpa'];
    $gpa        = (float)$_POST['gpa'];
    $cgpa       = (float)$_POST['cgpa'];
    $attendance = (float)$_POST['attendance'];
    $semesters->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($sem_id)],
        ['$set' => ['prev_gpa' => $prev_gpa, 'gpa' => $gpa, 'cgpa' => $cgpa, 'attendance' => $attendance, 'verified' => true]]
    );
    header("Location: semester_detail.php?id=$sem_id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Confirmation</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<nav class="navbar">
    <a href="dashboard.php" class="nav-brand">SGI</a>
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
    <h2>Confirmation – Semester <?= $sem['sem'] ?></h2>
    <hr style="margin:16px 0;">
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
        <label>GPA (out of 10)</label>
        <input type="number" name="gpa" step="0.01" min="0" max="10" value="<?= $sem['gpa'] ?? '' ?>" required>
        <label>CGPA (out of 10)</label>
        <input type="number" name="cgpa" step="0.01" min="0" max="10" value="<?= $sem['cgpa'] ?? '' ?>" required>
        <label>Attendance %</label>
        <input type="number" name="attendance" step="0.01" min="0" max="100" value="<?= $sem['attendance'] ?? '' ?>" required>
        <div class="declaration-box">
            <label class="declaration-label">
                <input type="checkbox" id="declaration" onchange="toggleSubmit()" required>
                I hereby declare that the above CAT marks, GPA, CGPA, and attendance information provided by me are true and correct to the best of my knowledge.
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
</script>
</body>
</html>


