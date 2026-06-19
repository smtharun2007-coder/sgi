<?php
include 'config.php';
requireLogin();

if (empty($_GET['sem_id'])) { header("Location: dashboard.php"); exit; }

$sem_id = $_GET['sem_id'];
$roll   = $_SESSION['user']['roll'];

$sem = $semesters->findOne(['_id' => new MongoDB\BSON\ObjectId($sem_id), 'roll' => $roll]);
if (!$sem) { header("Location: dashboard.php"); exit; }

// Get all subjects for this semester
$subCursor = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll]);
$subList   = iterator_to_array($subCursor);

// Separate internal and non-internal subjects
$internalSubs = [];
$nonInternalSubs = [];
foreach ($subList as $sub) {
    if ($sub['internal'] === 'yes') {
        $internalSubs[] = $sub;
    } else {
        $nonInternalSubs[] = $sub;
    }
}

$success = '';
$error = '';

// Handle adding new non-internal subject
if (isset($_POST['add_subject'])) {
    $name    = trim($_POST['new_subject_name']);
    $code    = trim($_POST['new_subject_code']);
    $credits = (int)$_POST['new_credits'];
    
    if (!empty($name) && !empty($code) && $credits > 0) {
        $subjects->insertOne([
            'sem_id'        => $sem_id,
            'roll'          => $roll,
            'subject_name'  => $name,
            'subject_code'  => $code,
            'credits'       => $credits,
            'internal'      => 'no',
            'cat1'          => null,
            'cat2'          => null,
            'cat3'          => null,
            'total'         => null,
            'percentage'    => null
        ]);
        $success = "Non-internal subject '$name' added successfully.";
        // Refresh subject list
        $subCursor = $subjects->find(['sem_id' => $sem_id, 'roll' => $roll]);
        $subList   = iterator_to_array($subCursor);
        $nonInternalSubs = [];
        foreach ($subList as $sub) {
            if ($sub['internal'] !== 'yes') {
                $nonInternalSubs[] = $sub;
            }
        }
    } else {
        $error = "Please fill all fields correctly.";
    }
}

// Handle deleting a non-internal subject
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $subjects->deleteOne(['_id' => new MongoDB\BSON\ObjectId($delete_id)]);
    $success = "Subject deleted successfully.";
    header("Location: credit_subjects.php?sem_id=$sem_id&success=1");
    exit;
}

// Handle confirming and moving to verify step
if (isset($_POST['confirm_credits'])) {
    $semesters->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($sem_id)],
        ['$set' => ['credits_done' => true]]
    );
    header("Location: verify_marks.php?sem_id=$sem_id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Credit Subjects</title>
    <link rel="stylesheet" href="/css/style.css?v=2">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<nav class="navbar">
<a href="dashboard.php" class="nav-brand">
    <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo">
    SGI
</a>
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
    <h2>Credit Subjects – Semester <?= $sem['sem'] ?></h2>
    <hr style="margin:16px 0;">
    <?php if (isset($_GET['success'])): ?><p class="success">Subject added/deleted successfully.</p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="error"><?= $error ?></p><?php endif; ?>
    
    <h3>Add Non-Internal Subject</h3>
    <p style="color:#888;font-size:14px;margin-bottom:15px;">Add subjects that don't have CAT marks (e.g., Environmental Science, Audit Courses, etc.)</p>
    <form method="POST" style="margin-bottom:20px;">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:2;min-width:150px;">
                <label>Subject Name</label>
                <input type="text" name="new_subject_name" placeholder="e.g., Environmental Science" style="width:100%;" required>
            </div>
            <div style="flex:1;min-width:100px;">
                <label>Subject Code</label>
                <input type="text" name="new_subject_code" placeholder="e.g., ECS101" style="width:100%;" required>
            </div>
            <div style="flex:1;min-width:80px;">
                <label>Credits</label>
                <input type="number" name="new_credits" min="1" max="6" value="2" style="width:100%;" required>
            </div>
            <div>
                <button type="submit" name="add_subject" class="btn-calc">+ Add Subject</button>
            </div>
        </div>
    </form>
    
    <?php if (!empty($nonInternalSubs)): ?>
    <h3>Added Non-Internal Subjects</h3>
    <div class="cat-table-wrap">
        <table class="cat-table">
            <thead>
                <tr>
                    <th>Subject Name</th>
                    <th>Code</th>
                    <th>Credits</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nonInternalSubs as $sub): $sid = (string)$sub['_id']; ?>
                <tr>
                    <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                    <td><?= htmlspecialchars($sub['subject_code']) ?></td>
                    <td><?= $sub['credits'] ?></td>
                    <td style="text-align:center;">
                        <a href="credit_subjects.php?sem_id=<?= $sem_id ?>&delete=<?= $sid ?>" 
                           onclick="return confirm('Are you sure you want to delete this subject?')" 
                           class="btn-remove" style="display:inline-block;text-decoration:none;padding:4px 10px;font-size:12px;">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p style="color:#888;text-align:center;padding:20px;">No non-internal subjects added yet.</p>
    <?php endif; ?>
    
    <hr style="margin:24px 0;">
    
    <div style="display:flex;gap:15px;justify-content:center;flex-wrap:wrap;">
        <form method="POST" style="display:inline;">
            <button type="submit" name="confirm_credits" class="btn-primary">Confirm & Proceed to Verify</button>
        </form>
        <a href="semester_detail.php?id=<?= $sem_id ?>" class="btn-secondary">Back to Semester</a>
    </div>
</div>
</div>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>