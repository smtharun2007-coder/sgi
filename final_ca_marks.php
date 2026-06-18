<?php
include 'config.php';
requireLogin();

if (empty($_GET['sem_id'])) { header("Location: dashboard.php"); exit; }

$sem_id = $_GET['sem_id'];
$roll   = $_SESSION['user']['roll'];

$sem = $semesters->findOne(['_id' => new MongoDB\BSON\ObjectId($sem_id), 'roll' => $roll]);
if (!$sem) { header("Location: dashboard.php"); exit; }
if (empty($sem['verified'])) { header("Location: semester_detail.php?id=$sem_id"); exit; }

$subCursor = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll]);
$subList   = iterator_to_array($subCursor);

$success = '';
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
    $semesters->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($sem_id)],
        ['$set' => ['ca_done' => true]]
    );
    $success = "Final CA marks saved successfully.";
    $subCursor = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll]);
    $subList   = iterator_to_array($subCursor);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Final CA Marks</title>
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
    <h2>Final CA Marks – Semester <?= $sem['sem'] ?></h2>
    <hr style="margin:16px 0;">
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

        <button type="submit" name="save_ca" class="btn-primary" style="margin-top:20px;">Save CA Marks</button>
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
</script>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>


