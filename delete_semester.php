<?php
include 'config.php';
requireLogin();

if (empty($_GET['id'])) { header("Location: dashboard.php"); exit; }

$id   = $_GET['id'];
$roll = $_SESSION['user']['roll'];
$s    = $semesters->findOne(['_id' => new MongoDB\BSON\ObjectId($id), 'roll' => $roll]);
if (!$s) { header("Location: dashboard.php"); exit; }

// Check if CAT 1 is submitted
$subCursor    = $subjects->find(['sem_id' => $id, 'roll' => $roll, 'internal' => 'yes']);
$internalSubs = iterator_to_array($subCursor);
foreach ($internalSubs as $sub) {
    if (isset($sub['cat1'])) {
        header("Location: dashboard.php");
        exit;
    }
}

if (isset($_POST['confirm_delete'])) {
    $subjects->deleteMany(['sem_id' => $id, 'roll' => $roll]);
    $semesters->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id), 'roll' => $roll]);
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Delete Semester</title>
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
<div class="form-box" style="max-width:500px;text-align:center;">
    <h2>Delete Semester <?= $s['sem'] ?>?</h2>
    <p style="color:#888;margin:16px 0;">This will permanently delete Semester <?= $s['sem'] ?> and all its subjects. This action cannot be undone.</p>
    <form method="POST">
        <button type="submit" name="confirm_delete" class="btn-primary" style="background:#e94560;">Yes, Delete</button>
    </form>
    <a href="dashboard.php" class="btn-secondary">Cancel</a>
</div>
</div>
</body>
</html>

