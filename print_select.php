<?php
include 'config.php';
requireLogin();

$u = $_SESSION['user'];
$semCursor = $semesters->find(['roll' => $u['roll']], ['sort' => ['sem' => 1]]);
$semList   = iterator_to_array($semCursor);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Print</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="logo1.jpeg">
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
<div class="form-box" style="max-width:600px;text-align:center;">
    <h2>Print Report</h2>
    <hr style="margin:16px 0;">

    <!-- OVERALL REPORT -->
    <div class="print-option-card">
        <div class="print-option-info">
            <h3>Overall Report</h3>
            <p>All semesters summary.</p>
        </div>
        <a href="print_dashboard.php" target="_blank" class="btn-primary" style="width:auto;padding:12px 30px;margin-top:0;font-size:15px;">Print</a>
    </div>

    <!-- SUBJECT REPORT -->
    <div class="print-option-card">
        <div class="print-option-info">
            <h3>All Subject Report</h3>
            <p>Best/worst semester, subject averages &amp; CA marks.</p>
        </div>
        <a href="print_subjects.php" target="_blank" class="btn-primary" style="width:auto;padding:12px 30px;margin-top:0;font-size:15px;">Print</a>
    </div>

    <hr style="margin:20px 0;">

    <!-- PER SEMESTER -->
    <h3 style="color:#1a1a2e;margin-bottom:12px;">Print by Semester</h3>
    <?php if (empty($semList)): ?>
        <p class="no-data">No semesters added yet.</p>
    <?php else: ?>
    <div class="print-sem-list">
        <?php foreach ($semList as $s): ?>
        <div class="print-option-card">
            <div class="print-option-info">
                <h3>Semester <?= $s['sem'] ?></h3>
            </div>
            <?php if (!empty($s['sgi'])): ?>
                <a href="print_report.php?id=<?= (string)$s['_id'] ?>" target="_blank" class="btn-calc" style="margin-top:0;padding:12px 30px;font-size:15px;">Print</a>
            <?php else: ?>
                <span class="btn-calc btn-disabled" style="margin-top:0;padding:12px 30px;font-size:15px;">SGI Pending</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div>
</body>
</html>

